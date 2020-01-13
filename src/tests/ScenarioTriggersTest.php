<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Crm\UsersModule\Auth\UserManager;

class ScenarioTriggersTest extends BaseTestCase
{
    /** @var UserManager */
    private $userManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->userManager = $this->inject(UserManager::class);
    }

    public function testTriggerUserCreatedScenario()
    {
        $scenarioRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_created'],
                ])
            ]
        ]);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(true); // process trigger

        $this->userManager->addNewUser('user2@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(true); // process trigger

        $this->userManager->addNewUser('user3@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(true); // process trigger

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Check stats
        // Triggers are only CREATED and then FINISHED
        $tsr = $this->getRepository(TriggerStatsRepository::class);
        $triggerStats = $tsr->countsFor($this->triggerId('trigger1'));
        $this->assertEquals(3, $triggerStats[JobsRepository::STATE_CREATED]);
        $this->assertEquals(3, $triggerStats[JobsRepository::STATE_FINISHED]);
    }
}
