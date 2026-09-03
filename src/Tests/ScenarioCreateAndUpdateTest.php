<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggerElementsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

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

    public function testTriggerUserRegisteredScenario()
    {
        $scenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10],
                ]),
            ],
        ]);

        $element = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait');
        $trigger = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_registered');

        $this->scenariosRepository->createOrUpdate([
            'id' => $scenario->id,
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 20],
                ]),
            ],
        ]);

        $updatedElement = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait');
        $updatedTrigger = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_registered');
        
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
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait2'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait2',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10],
                ]),
            ],
        ]);

        // Old element should be deleted + its link
        $this->assertEmpty($this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait'));
        $this->assertEmpty($this->triggerElementsRepository->getLink($trigger->id, $element->id));

        // New element should have different id (old one should be soft-deleted)
        $newElement = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait2');
        $this->assertNotEquals($newElement->id, $updatedElement->id);
    }

    public function testDeletedElementIsRevivedWhenItsUuidComesBack(): void
    {
        $scenario = $this->createWaitScenario();

        $element = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait');
        $trigger = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_registered');
        $elementId = $element->id;

        // the node is removed from the scenario - it is only soft-deleted, jobs and stats still reference it
        $this->saveWaitScenario($scenario->id, withElement: false);

        $this->assertNull($this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait'));
        $this->assertNotNull($this->elementsRepository->find($elementId)->deleted_at);

        // the very same uuid is saved again
        $this->saveWaitScenario($scenario->id, withElement: true, minutes: 20);

        $revived = $this->elementsRepository->findByScenarioIDAndElementUUID($scenario->id, 'element_wait');
        $this->assertNotNull($revived);
        // the row is reused, so everything pointing at the element id stays valid
        $this->assertSame($elementId, $revived->id);
        $this->assertNull($revived->deleted_at);
        $this->assertSame(20, Json::decode($revived->options, Json::FORCE_ARRAY)['minutes']);

        // and the link from the trigger is back
        $this->assertNotNull(
            $this->triggerElementsRepository->getTable()
                ->where(['trigger_id' => $trigger->id, 'element_id' => $elementId])
                ->fetch(),
        );
    }

    public function testDeletedTriggerIsRevivedWhenItsUuidComesBack(): void
    {
        $scenario = $this->createWaitScenario();

        $triggerId = $this->triggersRepository
            ->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_registered')->id;

        $scenarioData = $this->waitScenarioData($scenario->id, true, 10);
        $scenarioData['triggers'] = []; // remove trigger
        $this->scenariosRepository->createOrUpdate($scenarioData);

        $this->assertNull($this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_registered'));
        $this->assertNotNull($this->triggersRepository->find($triggerId)->deleted_at);

        $this->saveWaitScenario($scenario->id, withElement: true);

        $revived = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_user_registered');
        $this->assertNotNull($revived);
        $this->assertSame($triggerId, $revived->id);
        $this->assertNull($revived->deleted_at);
    }

    private function createWaitScenario(): ActiveRow
    {
        return $this->scenariosRepository->createOrUpdate($this->waitScenarioData(null, true, 10));
    }

    private function saveWaitScenario(int $scenarioId, bool $withElement, int $minutes = 10): void
    {
        $this->scenariosRepository->createOrUpdate($this->waitScenarioData($scenarioId, $withElement, $minutes));
    }

    private function waitScenarioData(?int $scenarioId, bool $withElement, int $minutes): array
    {
        $element = self::obj([
            'name' => '',
            'id' => 'element_wait',
            'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
            'wait' => ['minutes' => $minutes],
        ]);

        $data = [
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => $withElement ? ['element_wait'] : [],
                ]),
            ],
            'elements' => $withElement ? [$element] : [],
        ];

        if ($scenarioId !== null) {
            $data['id'] = $scenarioId;
        }

        return $data;
    }

    public function testElementCycleIsForbidden()
    {
        $this->expectException(\Exception::class);
        $this->scenariosRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_created',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_condition'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_condition',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'descendants' => [
                            ['uuid' => 'element_condition', 'direction' => 'positive'],
                        ],
                        'conditions' => [
                            'event' => 'subscription',
                            'version' => 1,
                            'nodes' => [
                                [
                                    'key' => 'type',
                                    'params' => [
                                        [
                                            'key' => 'type',
                                            'values' => [
                                                'selection'=> ['free'],
                                                'operator' => 'or',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ]);
    }
}
