<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class ScenariosRepository extends Repository
{
    protected $tableName = 'scenarios_scenarios';

    private $elementsRepository;

    private $elementElementsRepository;

    private $eventsRepository;

    private $triggersRepository;

    private $triggerElementsRepository;

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        ElementsRepository $elementsRepository,
        ElementElementsRepository $elementElementsRepository,
        EventsRepository $eventsRepository,
        TriggersRepository $triggersRepository,
        TriggerElementsRepository $triggerElementsRepository
    ) {
        parent::__construct($database, $cacheStorage);

        $this->elementsRepository = $elementsRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->eventsRepository = $eventsRepository;
        $this->triggersRepository = $triggersRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
    }

    /**
     * @param array $data
     * @return false|int - Returns false when scenarioID provided for update is not found
     * @throws ScenarioInvalidDataException - when unable to create / update scenario because of invalid data
     * @throws \Exception - when internal error occurs
     */
    public function createOrUpdate(array $data)
    {
        $scenarioData['name'] = $data['title'];
        $scenarioData['visual'] = Json::encode($data['visual']);
        $scenarioData['created_at'] = new DateTime();
        $scenarioData['modified_at'] = new DateTime();

        // save or update scenario details
        if (isset($data['id'])) {
            $scenario = $this->find($data['id']);
            if (!$scenario) {
                return false;
            }
            $this->update($scenario, $scenarioData);
        } else {
            $scenario = $this->insert($scenarioData);
            if (!$scenario) {
                // TODO: catch obvious errors from DB
                throw new \Exception("Unable to save scenario.");
            }
        }
        $scenarioID = $scenario->getPrimary();

        // TODO: move whole block to elements repository
        // add elements of scenario
        $this->elementsRepository->removeAllByScenario($scenarioID);
        $elementPairs = [];
        foreach ($data['elements'] as $element) {
            $elementData = [
                'scenario_id' => $scenarioID,
                'uuid' => $element->id,
                'name' => $element->title,
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
                    throw new ScenarioInvalidDataException("Unknown element type [{$element->type}].");
            }

            $this->elementsRepository->insert($elementData);
        }

        // TODO: move whole block to elementElements repository
        // process elements' descendants
        foreach ($elementPairs as $parent => $descendants) {
            $parentID = $this->elementsRepository->findBy('uuid', $parent)->getPrimary();

            if (!isset($descendants['positive'])) {
                continue;
            }
            foreach ($descendants['positive'] as $descendant) {
                $descendantID = $this->elementsRepository->findBy('uuid', $descendant)->getPrimary();
                $elementElementsData = [
                    'parent_element_id' => $parentID,
                    'child_element_id' => $descendantID,
                    'positive' => 1,
                ];
                $this->elementElementsRepository->insert($elementElementsData);
            }

            if (!isset($descendants['negative'])) {
                continue;
            }
            foreach ($descendants['negative'] as $descendant) {
                $descendantID = $this->elementsRepository->findBy('uuid', $descendant)->getPrimary();
                $elementElementsData = [
                    'parent_element_id' => $parentID,
                    'child_element_id' => $descendantID,
                    'positive' => 0,
                ];
                $this->elementElementsRepository->insert($elementElementsData);
            }
        }

        // TODO: move whole block to triggers repository
        // process triggers (root elements)
        $this->triggersRepository->removeAllByScenarioID($scenarioID);
        foreach ($data['triggers'] as $trigger) {
            if ($trigger->type !== TriggersRepository::TRIGGER_TYPE_EVENT) {
                throw new ScenarioInvalidDataException("Unknown trigger type [{$trigger->type}].");
            }
            $event = $this->eventsRepository->findBy('code', $trigger->event->code);
            if (!$event) {
                throw new ScenarioInvalidDataException("Unknown event type [{$trigger->event->code}].");
            }
            $triggerData = [
                'scenario_id' => $scenarioID,
                'event_id' => $event->getPrimary(),
                'uuid' => $trigger->id,
                'name' => $trigger->title,
            ];
            $newTrigger = $this->triggersRepository->insert($triggerData);

            // insert links from triggers
            foreach ($trigger->elements as $triggerElement) {
                $triggerElementID = $this->elementsRepository->findBy('uuid', $triggerElement)->getPrimary();
                $triggerElementData = [
                    'trigger_id' => $newTrigger->getPrimary(),
                    'element_id' => $triggerElementID,
                ];
            }

            $this->triggerElementsRepository->insert($triggerElementData);
        }

        return $scenarioID;
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
            'title' => $scenario->name,
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
                'title' => $scenarioTrigger->name,
                'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                'event' => [
                    'code' => $scenarioTrigger->event->code
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
                'title' => $scenarioElement->name,
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
