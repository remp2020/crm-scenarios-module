<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Tomaj\Hermes\Emitter;

class ScenarioTriggersTest extends BaseTestCase
{
    /** @var Emitter */
    private $hermesEmitter;

    public function setUp(): void
    {
        parent::setUp();
        $this->hermesEmitter = $this->inject(Emitter::class);
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

        $this->hermesEmitter->emit(new HermesMessage('user-created', [
            'user_id' => 1,
            'password' => 'SOMEPASS'
        ]));

        $this->dispatcher->handle();
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }
}
