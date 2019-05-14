<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class ScenariosRepository extends Repository
{
    protected $tableName = 'scenarios';

    private $connection;

    private $elementsRepository;

    private $elementElementsRepository;

    private $eventsRepository;

    private $eventsStorage;

    private $triggersRepository;

    private $triggerElementsRepository;

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        Connection $connection,
        ElementsRepository $elementsRepository,
        ElementElementsRepository $elementElementsRepository,
        EventsRepository $eventsRepository,
        EventsStorage $eventsStorage,
        TriggersRepository $triggersRepository,
        TriggerElementsRepository $triggerElementsRepository
    ) {
        parent::__construct($database, $cacheStorage);

        $this->connection = $connection;
        $this->elementsRepository = $elementsRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->eventsRepository = $eventsRepository;
        $this->eventsStorage = $eventsStorage;
        $this->triggersRepository = $triggersRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
    }

    /**
     * @param array $data
     * @return false|ActiveRow - Returns false when scenarioID provided for update is not found
     * @throws ScenarioInvalidDataException - when unable to create / update scenario because of invalid data
     * @throws \Exception - when internal error occurs
     */
    public function createOrUpdate(array $data)
    {
        $this->connection->beginTransaction();

        $scenarioData['name'] = $data['name'];
        $scenarioData['visual'] = Json::encode($data['visual']);
        $scenarioData['modified_at'] = new DateTime();

        // save or update scenario details
        if (isset($data['id'])) {
            $scenario = $this->find((int)$data['id']);
            if (!$scenario) {
                $this->connection->commit();
                return false;
            }
            $this->update($scenario, $scenarioData);
        } else {
            $scenarioData['created_at'] = $scenarioData['modified_at'];
            $scenario = $this->insert($scenarioData);
        }
        $scenarioID = $scenario->id;

        // remove old values
        $this->triggersRepository->removeAllByScenarioID($scenarioID);
        $this->elementsRepository->removeAllByScenarioID($scenarioID);

        // TODO: move whole block to elements repository
        // add elements of scenario
        $elementPairs = [];
        foreach ($data['elements'] as $element) {
            $elementData = [
                'scenario_id' => $scenarioID,
                'uuid' => $element->id,
                'name' => $element->name,
                'type' => $element->type,
            ];

            switch ($element->type) {
                case ElementsRepository::ELEMENT_TYPE_ACTION:
                    // TODO: check type of action?
                    $elementData['action_code'] = $element->action->email->code;
                    $elementPairs[$element->id]['descendants'] = $element->action->descendants;
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $elementData['segment_code'] = $element->segment->code;
                    $elementPairs[$element->id]['descendants'] = $element->segment->descendants;
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    $elementData['wait_time'] = $element->wait->minutes;
                    $elementPairs[$element->id]['descendants'] = $element->wait->descendants;
                    break;
                default:
                    $this->connection->rollback();
                    throw new ScenarioInvalidDataException("Unknown element type [{$element->type}].");
            }

            $this->elementsRepository->insert($elementData);
        }

        // TODO: move whole block to elementElements repository
        // process elements' descendants
        foreach ($elementPairs as $parentUUID => $element) {
            $parent = $this->elementsRepository->findBy('uuid', $parentUUID);
            if (!$parent) {
                $this->connection->rollback();
                throw new \Exception("Unable to find element with uuid [{$parentUUID}]");
            }

            foreach ($element['descendants'] as $descendantDef) {
                $descendant = $this->elementsRepository->findBy('uuid', $descendantDef->uuid);
                if (!$descendant) {
                    $this->connection->rollback();
                    throw new \Exception("Unable to find element with uuid [{$descendant->uuid}]");
                }

                $elementElementsData = [
                    'parent_element_id' => $parent->id,
                    'child_element_id' => $descendant->id,
                ];
                if (isset($descendantDef->segment)) {
                    $elementElementsData['positive'] = $descendantDef->segment->direction === 'positive' ? true : false;
                }
                $this->elementElementsRepository->insert($elementElementsData);
            }
        }

        // TODO: move whole block to triggers repository
        // process triggers (root elements)
        foreach ($data['triggers'] as $trigger) {
            if ($trigger->type !== TriggersRepository::TRIGGER_TYPE_EVENT) {
                $this->connection->rollback();
                throw new ScenarioInvalidDataException("Unknown trigger type [{$trigger->type}].");
            }
            if (!$this->eventsStorage->isEventPublic($trigger->event->code)) {
                $this->connection->rollback();
                throw new ScenarioInvalidDataException("Unknown event code [{$trigger->event->code}].");
            }

            $triggerData = [
                'scenario_id' => $scenarioID,
                'event_code' => $trigger->event->code,
                'uuid' => $trigger->id,
                'name' => $trigger->name,
            ];
            $newTrigger = $this->triggersRepository->insert($triggerData);

            // insert links from triggers
            $triggerElementData = false;
            foreach ($trigger->elements as $triggerElementUUID) {
                $triggerElement = $this->elementsRepository->findBy('uuid', $triggerElementUUID);
                if (!$triggerElement) {
                    $this->connection->rollback();
                    throw new \Exception("Unable to find element with uuid [{$triggerElementUUID}]");
                }
                $triggerElementData = [
                    'trigger_id' => $newTrigger->id,
                    'element_id' => $triggerElement->id,
                ];
            }
            if ($triggerElementData) {
                $this->triggerElementsRepository->insert($triggerElementData);
            }
        }

        $this->connection->commit();
        return $scenario;
    }

    public function getActiveScenarios()
    {
        return $this->getTable()->where('active', true);
    }

    /**
     * Load whole scenario with all triggers and elements.
     *
     * @param int $scenarioID
     *
     * @return array|false if scenario was not found
     * @throws \Nette\Utils\JsonException
     */
    public function getScenario(int $scenarioID)
    {
        $scenario = $this->find($scenarioID);
        if (!$scenario) {
            return false;
        }

        $result = [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'triggers' => $this->getTriggers($scenario),
            'elements' => $this->getElements($scenario),
            'visual' => Json::decode($scenario->visual, Json::FORCE_ARRAY),
        ];

        return $result;
    }

    private function getTriggers(ActiveRow $scenario): array
    {
        $triggers = [];
        foreach ($scenario->related('scenarios_triggers')->fetchAll() as $scenarioTrigger) {
            $trigger = [
                'id' => $scenarioTrigger->uuid,
                'name' => $scenarioTrigger->name,
                'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                'event' => [
                    'code' => $scenarioTrigger->event_code
                ],
                'elements' => [],
            ];

            foreach ($scenarioTrigger->related('scenarios_trigger_elements') as $triggerElement) {
                $trigger['elements'][] = $triggerElement->element->uuid;
            }

            $triggers[$scenarioTrigger->uuid] = $trigger;
        }

        return $triggers;
    }

    private function getElements(ActiveRow $scenario): array
    {
        $elements = [];
        foreach ($scenario->related('scenarios_elements')->fetchAll() as $scenarioElement) {
            $element = [
                'id' => $scenarioElement->uuid,
                'name' => $scenarioElement->name,
                'type' => $scenarioElement->type,
            ];

            $descendants = $this->getElementDescendants($scenarioElement);

            switch ($scenarioElement->type) {
                case ElementsRepository::ELEMENT_TYPE_ACTION:
                    $element[ElementsRepository::ELEMENT_TYPE_ACTION] = [
                        'type' => 'email',
                        'email' => [
                            'code' => $scenarioElement->action_code,
                        ],
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $element[ElementsRepository::ELEMENT_TYPE_SEGMENT] = [
                        'code' => $scenarioElement->segment_code,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    $element[ElementsRepository::ELEMENT_TYPE_WAIT] = [
                        'minutes' => $scenarioElement->wait_time,
                        'descendants' => $descendants,
                    ];
                    break;
                default:
                    throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - unknown element type [{$scenarioElement->type}].");
            }

            $elements[$scenarioElement->uuid] = $element;
        }

        return $elements;
    }

    private function getElementDescendants(ActiveRow $element): array
    {
        $descendants = [];
        foreach ($element->related('scenarios_element_elements.parent_element_id')->fetchAll() as $descendant) {
            $d = [
                'uuid' => $descendant->ref('scenarios_elements', 'child_element_id')->uuid,
            ];
            switch ($element->type) {
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $d[ElementsRepository::ELEMENT_TYPE_SEGMENT] = [
                        'direction' => ($descendant->positive == 1 || $descendant->positive === true) ? 'positive' : 'negative',
                    ];
                    break;
            }
            $descendants[] = $d;
        }
        return $descendants;
    }
}
