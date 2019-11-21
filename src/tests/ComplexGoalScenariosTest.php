<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Hermes\DummyDriver;
use Crm\OnboardingModule\Repository\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repository\UserOnboardingGoalsRepository;
use Crm\ScenariosModule\Events\OnboardingGoalsCheckEventHandler;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\Driver\DriverInterface;

class ComplexGoalScenariosTest extends BaseTestCase
{
    /**
     * Test scenario with TRIGGER -> GOAL -> MAIL (positive) flow
     */
    public function testGoalsCompletedScenario()
    {
        $this->enableDummyDispatcherTimeCheck();
        $this->insertScenario(['basic_goal']);

        $jr = $this->getRepository(JobsRepository::class);
        $user1 = $this->inject(UserManager::class)->addNewUser('test@email.com', false, 'unknown', null, false);
        $goal1 = $this->insertGoal('basic_goal');

        // SIMULATE ENGINE + HERMES RUN

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create goal job
        $this->engine->run(true); // job(goal): created -> scheduled

        // GOAL is not yet finished, reschedule
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> (re)scheduled

        $this->assertCount(1, $jr->getScheduledJobs()->fetchAll());

        // Complete goal
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user1->id, $goal1->id);

        // GOAL should be completed by now
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished

        // Check GOAL element results - user has to have completed goal
        $jobResults = Json::decode($jr->getFinishedJobs()->fetch()->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResults[OnboardingGoalsCheckEventHandler::RESULT_PARAM_GOALS_COMPLETED]);

        $this->engine->run(true); // job(goal) deleted, job(email) created
        $this->engine->run(true); // job(email) created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $mails = $this->mailsSentTo('test@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals('empty_template_code_pos', $mails[0]);

        $this->engine->run(true); // job(email) deleted
        $this->assertCount(0, $jr->getAllJobs()->fetchAll());
    }

    /**
     * Test scenario with TRIGGER -> 2 GOALs -> MULTI-USER COMPLETE -> MAIL (positive) flow
     */
    public function testGoalsCompletedScenarioMultiUser()
    {
        $this->enableDummyDispatcherTimeCheck();
        $this->insertScenario(['basic_goal1', 'basic_goal2']);

        /** @var JobsRepository $jr */
        $jr = $this->getRepository(JobsRepository::class);
        $user1 = $this->inject(UserManager::class)->addNewUser('test1@email.com', false, 'unknown', null, false);
        $user2 = $this->inject(UserManager::class)->addNewUser('test2@email.com', false, 'unknown', null, false);
        $user3 = $this->inject(UserManager::class)->addNewUser('test3@email.com', false, 'unknown', null, false);
        $goal1 = $this->insertGoal('basic_goal1');
        $goal2 = $this->insertGoal('basic_goal2');

        // SIMULATE ENGINE + HERMES RUN

        $this->dispatcher->handle(); // run Hermes to create trigger jobs
        $this->engine->run(true); // process user trigger, finish its job and create goal jobs
        $this->engine->run(true); // jobs(goal): created -> scheduled

        // GOAL is not yet finished, reschedule
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> (re)scheduled

        $this->assertCount(3, $jr->getScheduledJobs()->fetchAll());

        // Complete first goal
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user1->id, $goal1->id);
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user2->id, $goal1->id);

        // GOAL element shouldn't be completed yet, one more goal to finish
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished
        $this->assertFalse($jr->getFinishedJobs()->fetch());

        // Complete second goal for user2
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user2->id, $goal2->id);

        // Check GOAL element results - user has to have completed goal
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished
        $jobResults = $jr->getFinishedJobs()->fetchAll();
        $this->assertCount(1, $jobResults);
        $jobResult = Json::decode(reset($jobResults)->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResult[OnboardingGoalsCheckEventHandler::RESULT_PARAM_GOALS_COMPLETED]);

        $this->engine->run(true); // job(goal) deleted, job(email) created
        $this->engine->run(true); // job(email) created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $mails = $this->mailsSentTo('test2@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals('empty_template_code_pos', $mails[0]);

        $this->engine->run(true); // job(email) deleted
        $this->assertCount(2, $jr->getAllJobs()->fetchAll()); // user1 and user3 goal job still waiting

        // Finish the rest
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user1->id, $goal2->id);
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user3->id, $goal1->id);
        $this->getRepository(UserOnboardingGoalsRepository::class)->complete($user3->id, $goal2->id);
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished

        // Check GOAL element results - both remaining users should have completed goals
        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished
        $jobResults = $jr->getFinishedJobs()->fetchAll();
        $this->assertCount(2, $jobResults);
    }

    /**
     * Test scenario with TRIGGER -> GOAL -> MAIL (timeout) flow
     */
    public function testGoalsTimeoutScenario()
    {
        $this->enableDummyDispatcherTimeCheck();
        $this->insertScenario(['basic_goal']);
        $jr = $this->getRepository(JobsRepository::class);

        $this->inject(UserManager::class)->addNewUser('test2@email.com', false, 'unknown', null, false);

        $this->insertGoal('basic_goal');

        // SIMULATE ENGINE + HERMES RUN

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create goal job
        $this->engine->run(true); // job(goal): created -> schedule

        $this->dispatcher->handle(); // job(goal): scheduled -> started -> scheduled
        $this->assertCount(1, $jr->getScheduledJobs()->fetchAll()); // Assert job is rescheduled

        // Simulate timeout (move job's 'created_at' 20 minutes to past)
        $job = $jr->getScheduledJobs()->fetch();
        $jr->update($job, ['created_at' => new DateTime('now - 20 minutes')]);

        $this->dispatcher->handle(); // job(goal): scheduled -> started -> finished (timeouted)

        // Assert GOAL check was timeouted
        $jobResults = Json::decode($jr->getFinishedJobs()->fetch()->result, Json::FORCE_ARRAY);
        $this->assertTrue($jobResults[OnboardingGoalsCheckEventHandler::RESULT_PARAM_TIMEOUT]);

        $this->engine->run(true); // job(goal) deleted, job(email) created
        $this->engine->run(true); // job(email) created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $mails = $this->mailsSentTo('test2@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals('empty_template_code_neg', $mails[0]);
        $this->engine->run(true); // job(email) deleted
        $this->assertCount(0, $jr->getAllJobs()->fetchAll());
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
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_goal']
                ])
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
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email1',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code_pos']
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email2',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code_neg']
                ])
            ]
        ]);
    }

    private function insertGoal($code)
    {
        $onboardingGoalsRepo = $this->getRepository(OnboardingGoalsRepository::class);
        $goal = $onboardingGoalsRepo->add([
            'code' => $code,
            'name' => '',
            'type' => OnboardingGoalsRepository::TYPE_SIMPLE,
        ]);
        return $goal;
    }
}
