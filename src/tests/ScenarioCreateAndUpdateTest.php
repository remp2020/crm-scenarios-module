<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggerElementsRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;

class ScenarioCreateAndUpdateTest extends BaseTestCase
{
    /** @var ScenariosRepository */
    private $scenariosRepository;

    /** @var TriggersRepository */
    private $triggersRepository;

    /** @var ElementsRepository */
    private $elementsRepository;

    /** @var TriggerElementsRepository */
    private $triggerElementsRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->triggersRepository = $this->getRepository(TriggersRepository::class);
        $this->elementsRepository = $this->getRepository(ElementsRepository::class);
        $this->triggerElementsRepository = $this->getRepository(TriggerElementsRepository::class);
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
        $trigger = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_created');

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
        $updatedTrigger = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_created');
        
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

        // Old element should be deleted + its link
        $this->assertEmpty($this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait'));
        $this->assertEmpty($this->triggerElementsRepository->getLink($trigger->id, $element->id));

        // New element should have different id (old one should be soft-deleted)
        $newElement = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait2');
        $this->assertNotEquals($newElement->id, $updatedElement->id);
    }
}
