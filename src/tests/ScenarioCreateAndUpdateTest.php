<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;

class ScenarioCreateAndUpdateTest extends BaseTestCase
{
    /** @var ScenariosRepository */
    private $scenariosRepository;

    /** @var TriggersRepository */
    private $triggersRepository;

    /** @var ElementsRepository */
    private $elementsRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->triggersRepository = $this->getRepository(TriggersRepository::class);
        $this->elementsRepository = $this->getRepository(ElementsRepository::class);
    }

    public function testTriggerUserCreatedScenario()
    {
        $scenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_created',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_wait']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10]
                ])
            ]
        ]);

        $element = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait');
        $trigger = $this->triggersRepository->findByScenarioIDAndTriggerUUID($scenario->id, 'trigger_user_created');

        $this->scenariosRepository->createOrUpdate([
            'id' => $scenario->id,
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_created',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_wait']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 20]
                ])
            ]
        ]);

        $updatedElement = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait');
        $updatedTrigger = $this->triggersRepository->findByScenarioIDAndTriggerUUID($scenario->id, 'trigger_user_created');
        
        // Check that when element is updated and keeps the same UUID, actual database ID (primary key) doesn't change
        $this->assertEquals($element->id, $updatedElement->id);
        $this->assertEquals($trigger->id, $updatedTrigger->id);

        $this->scenariosRepository->createOrUpdate([
            'id' => $scenario->id,
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_created',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_wait2']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait2',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10]
                ])
            ]
        ]);

        $this->assertEmpty($this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait'));

        $newElement = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait2');
        $this->assertNotEquals($newElement->id, $updatedElement->id);
    }
}
