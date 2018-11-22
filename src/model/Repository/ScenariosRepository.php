<?php

namespace Crm\ScenariosModule\Repository;

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

    private $triggersRepository;

    private $triggerElementsRepository;

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        Connection $connection,
        ElementsRepository $elementsRepository,
        ElementElementsRepository $elementElementsRepository,
        EventsRepository $eventsRepository,
        TriggersRepository $triggersRepository,
        TriggerElementsRepository $triggerElementsRepository
    ) {
        parent::__construct($database, $cacheStorage);

        $this->connection = $connection;
        $this->elementsRepository = $elementsRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->eventsRepository = $eventsRepository;
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
        $this->elementsRepository->removeAllByScenario($scenarioID);
        $this->triggersRepository->removeAllByScenarioID($scenarioID);

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
                    $elementPairs[$element->id]['positive'] = $element->action->descendants;
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $elementData['segment_code'] = $element->segment->code;
                    $elementPairs[$element->id]['positive'] = $element->segment->descendants_positive;
                    $elementPairs[$element->id]['negative'] = $element->segment->descendants_negative;
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    $elementData['wait_time'] = $element->wait->minutes;
                    $elementPairs[$element->id]['positive'] = $element->wait->descendants;
                    break;
                default:
                    $this->connection->rollback();
                    throw new ScenarioInvalidDataException("Unknown element type [{$element->type}].");
            }

            $this->elementsRepository->insert($elementData);
        }

        // TODO: move whole block to elementElements repository
        // process elements' descendants
        foreach ($elementPairs as $parentUUID => $descendants) {
            $parent = $this->elementsRepository->findBy('uuid', $parentUUID);
            if (!$parent) {
                $this->connection->rollback();
                throw new \Exception("Unable to find element with uuid [{$parentUUID}]");
            }

            if (!isset($descendants['positive'])) {
                continue;
            }
            foreach ($descendants['positive'] as $descendantUUID) {
                $descendant = $this->elementsRepository->findBy('uuid', $descendantUUID);
                if (!$descendant) {
                    $this->connection->rollback();
                    throw new \Exception("Unable to find element with uuid [{$descendantUUID}]");
                }
                $elementElementsData = [
                    'parent_element_id' => $parent->id,
                    'child_element_id' => $descendant->id,
                    'positive' => 1,
                ];
                $this->elementElementsRepository->insert($elementElementsData);
            }

            if (!isset($descendants['negative'])) {
                continue;
            }
            foreach ($descendants['negative'] as $descendantUUID) {
                $descendant = $this->elementsRepository->findBy('uuid', $descendantUUID);
                if (!$descendant) {
                    $this->connection->rollback();
                    throw new \Exception("Unable to find element with uuid [{$descendantUUID}]");
                }
                $elementElementsData = [
                    'parent_element_id' => $parent->id,
                    'child_element_id' => $descendant->id,
                    'positive' => 0,
                ];
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

            // TODO: add proper validation of event code after registration is implemented
            if (!in_array($trigger->event->code, ['user_created', 'new_payment'])) {
                $this->connection->rollback();
                throw new ScenarioInvalidDataException("Unknown event type [{$trigger->event->code}].");
            }
            $triggerData = [
                'scenario_id' => $scenarioID,
                'event_code' => $trigger->event->code,
                'uuid' => $trigger->id,
                'name' => $trigger->name,
            ];
            $newTrigger = $this->triggersRepository->insert($triggerData);

            // insert links from triggers
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

            $this->triggerElementsRepository->insert($triggerElementData);
        }

        $this->connection->commit();
        return $scenario;
    }


    /**
     * Load whole scenario with all triggers and elements.
     *
     * @param int $scenarioID
     * @return array|false if scenario was not found
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
                    ];
                    $element[ElementsRepository::ELEMENT_TYPE_ACTION]['descendants'] = array_merge(
                        array_keys($descendants['descendants_positive']),
                        array_keys($descendants['descendants_negative'])
                    );
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $element[ElementsRepository::ELEMENT_TYPE_SEGMENT] = [
                        'code' => $scenarioElement->segment_code,
                    ];
                    $element[ElementsRepository::ELEMENT_TYPE_SEGMENT]['descendants_positive'] = $descendants['descendants_positive'];
                    $element[ElementsRepository::ELEMENT_TYPE_SEGMENT]['descendants_negative'] = $descendants['descendants_negative'];
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    $element[ElementsRepository::ELEMENT_TYPE_WAIT] = [
                        'minutes' => $scenarioElement->wait_time,
                    ];
                    $element[ElementsRepository::ELEMENT_TYPE_WAIT]['descendants'] = array_merge(
                        $descendants['descendants_positive'],
                        $descendants['descendants_negative']
                    );
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
        $descendantsPositive = [];
        $descendantsNegative = [];
        foreach ($element->related('scenarios_element_elements.parent_element_id')->fetchAll() as $descendant) {
            $uuid = $descendant->ref('scenarios_elements', 'child_element_id')->uuid;
            if ($descendant->positive == 1 || $descendant->positive === true) {
                $descendantsPositive[] = $uuid;
            } else {
                $descendantsNegative[] = $uuid;
            }
        }
        return [
            'descendants_positive' => $descendantsPositive,
            'descendants_negative' => $descendantsNegative,
        ];
    }
}
