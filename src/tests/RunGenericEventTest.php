<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\ScenariosModule\Events\ScenariosGenericEventsManager;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\UsersModule\Auth\UserManager;

class RunGenericEventTest extends BaseTestCase
{
    /** @var ScenariosGenericEventsManager */
    protected $genericEventManager;

    protected $triggerGenericActionEventHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->genericEventManager = $this->inject(ScenariosGenericEventsManager::class);
        $this->triggerGenericActionEventHandler = new TriggerGenericActionEventHandler();
    }

    public function testSimpleGenericEventTriggeredWithoutCriteria()
    {
        $this->genericEventManager->register('test_generic_event_code', new class implements ScenarioGenericEventInterface {
            public function getLabel(): string
            {
                return 'test';
            }

            public function getParams(): array
            {
                return [];
            }

            public function createEvents($options, $params): array
            {
                return [new TriggerGenericActionEvent()];
            }
        });

        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test_generic',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_run_generic']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_run_generic',
                    'type' => ElementsRepository::ELEMENT_TYPE_GENERIC,
                    'generic' => ['code' => 'test_generic_event_code']
                ]),
            ]
        ]);

        $this->emitter->addListener(TriggerGenericActionEvent::class, $this->triggerGenericActionEventHandler);

        $this->inject(UserManager::class)->addNewUser('user1@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // process trigger, finish its job and create+schedule condition job
        $this->dispatcher->handle(); // job(generic): scheduled -> started -> finished

        $this->assertTrue($this->triggerGenericActionEventHandler->eventWasTriggered);
    }
}
