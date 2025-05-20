<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Hermes\DummyDriver;
use Crm\OnboardingModule\Repositories\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repositories\UserOnboardingGoalsRepository;
use Crm\ScenariosModule\Events\OnboardingGoalsCheckEventHandler;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\Driver\DriverInterface;

class ComplexGoalScenariosTest extends BaseTestCase
{
    /** @var UserOnboardingGoalsRepository */
    private $userOnboardingGoalsRepository;

    /** @var JobsRepository */
    private $jobsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->userOnboardingGoalsRepository = $this->getRepository(UserOnboardingGoalsRepository::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);
    }

    /**
     * Test scenario with TRIGGER -> GOAL -> MAIL (positive) flow
     */
    public function testGoalsCompletedScenario()
    {
        list($user, $goal) = $this->initStandardTest();

        // assert user doesn't have entry in user_onboarding_goals
        $this->assertNull($this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id));

        $this->simulateEngineAndHermes();

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        // assert entry in user_onboarding_goal was created by scenario
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($userOnboardingGoal);
        $this->assertNull($userOnboardingGoal->completed_at);
        $this->assertNull($userOnboardingGoal->timedout_at);

        // Complete goal
        $completedAt = new DateTime();
        $this->userOnboardingGoalsRepository->complete($user->id, $goal->id, $completedAt);

        // GOAL should be completed by now
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished

        // Check GOAL element results - user has to have completed goal
        $jobResults = Json::decode($this->jobsRepository->getFinishedJobs()->fetch()->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResults[OnboardingGoalsCheckEventHandler::RESULT_PARAM_GOALS_COMPLETED]);

        $this->engine->run(3); // job(goal) deleted, job(email) created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $mails = $this->mailsSentTo($user->email);
        $this->assertCount(1, $mails);
        $this->assertEquals('empty_template_code_pos', $mails[0]);

        $this->engine->run(1); // job(email) deleted
        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());

        // assert scenario didn't modify user's onboarding goal entry
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($userOnboardingGoal);
        $this->assertNull($userOnboardingGoal->timedout_at);
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $userOnboardingGoal->completed_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test scenario with TRIGGER -> 2 GOALs -> MULTI-USER COMPLETE -> MAIL (positive) flow
     */
    public function testGoalsCompletedScenarioMultiUser()
    {
        $this->enableDummyDispatcherTimeCheck();
        $this->insertScenario(['basic_goal1', 'basic_goal2']);

        $user1 = $this->inject(UserManager::class)->addNewUser('test1@email.com', false, 'unknown', null, false);
        $user2 = $this->inject(UserManager::class)->addNewUser('test2@email.com', false, 'unknown', null, false);
        $user3 = $this->inject(UserManager::class)->addNewUser('test3@email.com', false, 'unknown', null, false);
        $goal1 = $this->insertGoal('basic_goal1');
        $goal2 = $this->insertGoal('basic_goal2');

        $this->simulateEngineAndHermes();

        $this->assertCount(3, $this->jobsRepository->getScheduledJobs()->fetchAll());

        // Complete first goal
        $this->userOnboardingGoalsRepository->complete($user1->id, $goal1->id);
        $this->userOnboardingGoalsRepository->complete($user2->id, $goal1->id);

        // GOAL element shouldn't be completed yet, one more goal to finish
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished
        $this->assertNull($this->jobsRepository->getFinishedJobs()->fetch());

        // Complete second goal for user2
        $this->userOnboardingGoalsRepository->complete($user2->id, $goal2->id);

        // Check GOAL element results - user has to have completed goal
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished
        $jobResults = $this->jobsRepository->getFinishedJobs()->fetchAll();
        $this->assertCount(1, $jobResults);
        $jobResult = Json::decode(reset($jobResults)->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResult[OnboardingGoalsCheckEventHandler::RESULT_PARAM_GOALS_COMPLETED]);

        $this->engine->run(3); // job(goal) deleted, job(email) created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $mails = $this->mailsSentTo($user2->email);
        $this->assertCount(1, $mails);
        $this->assertEquals('empty_template_code_pos', $mails[0]);

        $this->engine->run(1); // job(email) deleted
        $this->assertCount(2, $this->jobsRepository->getAllJobs()->fetchAll()); // user1 and user3 goal job still waiting

        // Finish the rest
        $this->userOnboardingGoalsRepository->complete($user1->id, $goal2->id);
        $this->userOnboardingGoalsRepository->complete($user3->id, $goal1->id);
        $this->userOnboardingGoalsRepository->complete($user3->id, $goal2->id);
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished

        // Check GOAL element results - both remaining users should have completed goals
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished
        $jobResults = $this->jobsRepository->getFinishedJobs()->fetchAll();
        $this->assertCount(2, $jobResults);
    }

    /**
     * Test scenario with TRIGGER -> GOAL -> MAIL (timeout) flow
     */
    public function testGoalsTimeoutScenario()
    {
        list($user, $goal) = $this->initStandardTest();

        // assert user doesn't have entry in user_onboarding_goals
        $this->assertNull($this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id));

        $this->simulateEngineAndHermes();

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll()); // Assert job is rescheduled

        // assert user's user_onboarding_goals entry was created; but is not timed out / completed yet
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($userOnboardingGoal);
        $this->assertNull($userOnboardingGoal->completed_at);
        $this->assertNull($userOnboardingGoal->timedout_at);

        $this->simulateTimeout();

        // Assert GOAL check was timed out
        $jobResults = Json::decode($this->jobsRepository->getFinishedJobs()->fetch()->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResults[OnboardingGoalsCheckEventHandler::RESULT_PARAM_TIMEOUT]);

        $this->engine->run(3); // job(goal) deleted, job(email) created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $mails = $this->mailsSentTo($user->email);
        $this->assertCount(1, $mails);
        $this->assertEquals('empty_template_code_neg', $mails[0]);
        $this->engine->run(1); // job(email) deleted
        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());

        // assert user's user_onboarding_goals entry was timed out
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($userOnboardingGoal);
        $this->assertNull($userOnboardingGoal->completed_at);
        $this->assertNotNull($userOnboardingGoal->timedout_at);
    }

    /**
     * Test start of scenario for user with goal created (not completed or timed out) "before" job was created.
     * - previous goal is used; updated_at field contains updated time
     */
    public function testPreviousUserOnboardingGoalNotCompletedOrTimedOut()
    {
        list($user, $goal) = $this->initStandardTest();

        $now = new DateTime();
        $completedAt = null;
        $timedoutAt = null;
        $createdAt = (new DateTime())->modify('-1 week');
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->insert([
            'user_id' => $user->id,
            'onboarding_goal_id' => $goal->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
            'timedout_at' => $timedoutAt,
        ]);
        // previous goal was updated in past
        $this->assertGreaterThan($userOnboardingGoal->updated_at, $now);

        $this->simulateEngineAndHermes();

        // check results after scenario (see test description)
        $lastUserOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($lastUserOnboardingGoal);
        $this->assertEquals($userOnboardingGoal->id, $lastUserOnboardingGoal->id);
        $this->assertNull($lastUserOnboardingGoal->completed_at);
        $this->assertNull($lastUserOnboardingGoal->timedout_at);
        // goal was updated; new entry has "greater" datetime in updated_at
        $this->assertGreaterThan($userOnboardingGoal->updated_at, $lastUserOnboardingGoal->updated_at);
    }

    /**
     * Test start of scenario for user with goal created and completed "before" job was created.
     * - new `user_onboarding_goals` entry is created
     * - completed_at of previous goal is not changed
     */
    public function testPreviousUserOnboardingGoalCompleted()
    {
        list($user, $goal) = $this->initStandardTest();

        $completedAt = (new DateTime())->modify('-1 day');
        $timedoutAt = null;
        $createdAt = (new DateTime())->modify('-1 week');
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->insert([
            'user_id' => $user->id,
            'onboarding_goal_id' => $goal->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
            'timedout_at' => $timedoutAt,
        ]);

        $this->simulateEngineAndHermes();

        // check results after scenario (see test description)
        $userOnboardingGoalAfter = $this->userOnboardingGoalsRepository->find($userOnboardingGoal->id);
        $this->assertNotNull($userOnboardingGoalAfter);
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $userOnboardingGoalAfter->completed_at->format('Y-m-d H:i:s'));
        $this->assertNull($userOnboardingGoalAfter->timedout_at);

        $lastUserOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($lastUserOnboardingGoal);
        $this->assertNotEquals($userOnboardingGoal->id, $lastUserOnboardingGoal->id);
    }

    /**
     * Test start of scenario for user with goal created and timed out "before" job was created.
     * - new `user_onboarding_goals` entry is created
     * - timeout of previous goal is not changed
     */
    public function testPreviousUserOnboardingGoalTimedOut()
    {
        list($user, $goal) = $this->initStandardTest();

        $completedAt = null;
        $timedoutAt = (new DateTime())->modify('-1 day');
        $createdAt = (new DateTime())->modify('-1 week');
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->insert([
            'user_id' => $user->id,
            'onboarding_goal_id' => $goal->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
            'timedout_at' => $timedoutAt,
        ]);

        $this->simulateEngineAndHermes();

        // check results after scenario (see test description)
        $userOnboardingGoalAfter = $this->userOnboardingGoalsRepository->find($userOnboardingGoal->id);
        $this->assertNotNull($userOnboardingGoalAfter);
        $this->assertNull($userOnboardingGoalAfter->completed_at);
        $this->assertEquals($timedoutAt->format('Y-m-d H:i:s'), $userOnboardingGoalAfter->timedout_at->format('Y-m-d H:i:s'));

        $lastUserOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($lastUserOnboardingGoal);
        $this->assertNotEquals($userOnboardingGoal->id, $lastUserOnboardingGoal->id);
    }

    /**
     * Test scenario for user with goal created (not completed or timed out) "after" job was created.
     * - no new `user_onboarding_goals` entry is created
     */
    public function testCurrentUserOnboardingGoal()
    {
        list($user, $goal) = $this->initStandardTest();

        $completedAt = null;
        $timedoutAt = null;
        $createdAt = (new DateTime())->modify('+1 minute');
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->insert([
            'user_id' => $user->id,
            'onboarding_goal_id' => $goal->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
            'timedout_at' => $timedoutAt,
        ]);

        $this->simulateEngineAndHermes();

        // check results after scenario (see test description)
        $userOnboardingGoalAfter = $this->userOnboardingGoalsRepository->find($userOnboardingGoal->id);
        $this->assertNotNull($userOnboardingGoalAfter);
        $this->assertNull($userOnboardingGoalAfter->completed_at);
        $this->assertNull($userOnboardingGoalAfter->timedout_at);

        $lastUserOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($lastUserOnboardingGoal);
        $this->assertEquals($userOnboardingGoal->id, $lastUserOnboardingGoal->id);
    }

    /**
      * Test successful completion of scenario for user with goal created and completed "after" job was created.
      * - no new `user_onboarding_goals` entry is created
      * - user's onboarding goal entry is not changed by scenario
      */
    public function testCurrentCompletedUserOnboardingGoalFinishesJob()
    {
        list($user, $goal) = $this->initStandardTest();

        $completedAt = (new DateTime())->modify('+1 minute');
        $timedoutAt = null;
        $createdAt = (new DateTime())->modify('+1 minute');
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->insert([
            'user_id' => $user->id,
            'onboarding_goal_id' => $goal->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
            'timedout_at' => $timedoutAt,
        ]);

        $this->simulateEngineAndHermes();

        // job should be finished
        $jobResults = $this->jobsRepository->getFinishedJobs()->fetchAll();
        $this->assertCount(1, $jobResults);
        $jobResult = Json::decode(reset($jobResults)->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResult[OnboardingGoalsCheckEventHandler::RESULT_PARAM_GOALS_COMPLETED]);

        // check results after scenario (see test description)
        $userOnboardingGoalAfter = $this->userOnboardingGoalsRepository->find($userOnboardingGoal->id);
        $this->assertNotNull($userOnboardingGoalAfter);
        $this->assertEquals($completedAt->format('Y-m-d H:i:s'), $userOnboardingGoalAfter->completed_at->format('Y-m-d H:i:s'));
        $this->assertNull($userOnboardingGoalAfter->timedout_at);

        $lastUserOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($lastUserOnboardingGoal);
        $this->assertEquals($userOnboardingGoal->id, $lastUserOnboardingGoal->id);
    }

    /**
      * Test timeout of scenario for user with goal created "after" job was created.
      * - no new `user_onboarding_goals` entry is created
      * - user's onboarding goal entry is timed out by scenario
      */
    public function testCurrentUserOnboardingGoalTimeout()
    {
        list($user, $goal) = $this->initStandardTest();

        $completedAt = null;
        $timedoutAt = null;
        $createdAt = (new DateTime())->modify('+1 minute');
        $userOnboardingGoal = $this->userOnboardingGoalsRepository->insert([
            'user_id' => $user->id,
            'onboarding_goal_id' => $goal->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'completed_at' => $completedAt,
            'timedout_at' => $timedoutAt,
        ]);

        $this->simulateEngineAndHermes();

        $this->simulateTimeout();

        // assert GOAL check was timed out
        $jobResults = Json::decode($this->jobsRepository->getFinishedJobs()->fetch()->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResults[OnboardingGoalsCheckEventHandler::RESULT_PARAM_TIMEOUT]);

        // check results after scenario (see test description)
        $userOnboardingGoalAfter = $this->userOnboardingGoalsRepository->find($userOnboardingGoal->id);
        $this->assertNotNull($userOnboardingGoalAfter);
        $this->assertNull($userOnboardingGoalAfter->completed_at);
        $this->assertNotNull($userOnboardingGoalAfter->timedout_at);

        $lastUserOnboardingGoal = $this->userOnboardingGoalsRepository->userLastGoal($user->id, $goal->id);
        $this->assertNotNull($lastUserOnboardingGoal);
        $this->assertEquals($userOnboardingGoal->id, $lastUserOnboardingGoal->id);
    }

    /** HELPER FUNCTIONS */

    private function initStandardTest()
    {
        $this->enableDummyDispatcherTimeCheck();
        $this->insertScenario(['basic_goal1']);

        $user = $this->inject(UserManager::class)->addNewUser('test1@email.com', false, 'unknown', null, false);
        $goal = $this->insertGoal('basic_goal1');

        return [$user, $goal];
    }

    private function simulateEngineAndHermes()
    {
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // process trigger, finish its job and create + schedule goal job
        // GOAL is not yet finished, reschedule
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> (re)scheduled
    }

    private function simulateTimeout()
    {
        // Simulate timeout (move job's 'created_at' 20 minutes to past)
        $job = $this->jobsRepository->getScheduledJobs()->fetch();
        $this->jobsRepository->update($job, ['created_at' => new DateTime('now - 20 minutes')]);

        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished (timed out)
    }

    // Enables Hermes Dummy dispatcher enable_at check (goal element rescheduling relies on this feature)
    private function enableDummyDispatcherTimeCheck()
    {
        $hermesDriver = $this->inject(DriverInterface::class);
        if ($hermesDriver instanceof DummyDriver) {
            $hermesDriver->enableExecuteAtCheck(10 * 60); // 10 minutes
        }
    }

    private function insertScenario(array $requiredGoals)
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_goal'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_goal',
                    'type' => ElementsRepository::ELEMENT_TYPE_GOAL,
                    'goal' => [
                        'codes' => $requiredGoals,
                        'descendants' => [
                            ['uuid' => 'element_email1', 'direction' => 'positive'],
                            ['uuid' => 'element_email2', 'direction' => 'negative'],
                        ],
                        'recheckPeriodMinutes' => 5,
                        'timeoutMinutes' => 15,
                    ],
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email1',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code_pos'],
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email2',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code_neg'],
                ]),
            ],
        ]);
    }

    private function insertGoal($code)
    {
        $onboardingGoalsRepo = $this->getRepository(OnboardingGoalsRepository::class);
        $goal = $onboardingGoalsRepo->add($code, '', OnboardingGoalsRepository::TYPE_SIMPLE);
        return $goal;
    }
}
