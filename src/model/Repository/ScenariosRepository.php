<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
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

    private $eventsStorage;

    private $triggersRepository;

    private $triggerElementsRepository;

    public function __construct(
        Context $database,
        AuditLogRepository $auditLogRepository,
        IStorage $cacheStorage = null,
        Connection $connection,
        ElementsRepository $elementsRepository,
        ElementElementsRepository $elementElementsRepository,
        EventsStorage $eventsStorage,
        TriggersRepository $triggersRepository,
        TriggerElementsRepository $triggerElementsRepository
    ) {
        parent::__construct($database, $cacheStorage);

        $this->connection = $connection;
        $this->elementsRepository = $elementsRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->eventsStorage = $eventsStorage;
        $this->triggersRepository = $triggersRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function all()
    {
        return $this->getTable()->order('name ASC');
    }

    /**
     * @param array $data
     * @return false|ActiveRow - Returns false when scenarioID provided for update is not found
     * @throws ScenarioInvalidDataException - when unable to create / update scenario because of invalid data
     * @throws \Exception - when internal error occurs
     */
    final public function createOrUpdate(array $data)
    {
        $this->connection->beginTransaction();

        $scenarioData['name'] = $data['name'];
        $scenarioData['visual'] = Json::encode($data['visual'] ?? new \stdClass());
        $scenarioData['modified_at'] = new DateTime();
        if (isset($data['enabled'])) {
            $scenarioData['enabled'] = $data['enabled'];
        }

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
            // If not specified, by default not enabled
            $scenarioData['enabled'] = $scenarioData['enabled'] ?? false;
            $scenario = $this->insert($scenarioData);
        }
        $scenarioId = $scenario->id;

        $oldTriggers = $this->triggersRepository->allScenarioTriggers($scenarioId)->fetchPairs('uuid', 'id');
        $oldElements = $this->elementsRepository->allScenarioElements($scenarioId)->fetchPairs('uuid', 'id');

        // Delete all links
        $this->triggerElementsRepository->deleteLinksForTriggers(array_values($oldTriggers));
        $this->triggerElementsRepository->deleteLinksForElements(array_values($oldElements));
        $this->elementElementsRepository->deleteLinksForElements(array_values($oldElements));

        // TODO: move whole block to elements repository
        // add elements of scenario
        $elementPairs = [];
        foreach ($data['elements'] ?? [] as $element) {
            $elementData = [
                'scenario_id' => $scenarioId,
                'uuid' => $element->id,
                'name' => $element->name,
                'type' => $element->type,
            ];
            unset($oldElements[$element->id]);

            $elementOptions = null;
            switch ($element->type) {
                case ElementsRepository::ELEMENT_TYPE_EMAIL:
                    if (!isset($element->email->code)) {
                        throw new ScenarioInvalidDataException("Missing 'code' parameter for the Email node.");
                    }
                    $elementOptions = [
                        'code' => $element->email->code
                    ];
                    $elementPairs[$element->id]['descendants'] = $element->email->descendants ?? [];
                    break;
                case ElementsRepository::ELEMENT_TYPE_BANNER:
                    if (!isset($element->banner->id)) {
                        throw new ScenarioInvalidDataException("Missing 'id' parameter for the Banner node.");
                    }
                    if (!isset($element->banner->expiresInMinutes)) {
                        throw new ScenarioInvalidDataException("Missing 'expiresInMinutes' parameter for the Banner node.");
                    }
                    $elementOptions = [
                        'id' => $element->banner->id,
                        'expiresInMinutes' => $element->banner->expiresInMinutes,
                    ];
                    $elementPairs[$element->id]['descendants'] = $element->banner->descendants ?? [];
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    if (!isset($element->segment->code)) {
                        throw new ScenarioInvalidDataException("Missing 'code' parameter for the Segment node.");
                    }
                    $elementOptions = [
                        'code' => $element->segment->code
                    ];
                    $elementPairs[$element->id]['descendants'] = $element->segment->descendants ?? [];
                    break;
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                    if (!isset($element->condition->conditions)) {
                        throw new ScenarioInvalidDataException("Missing 'conditions' parameter for the Condition node.");
                    }
                    $elementOptions = [
                        'conditions' => $element->condition->conditions
                    ];
                    $elementPairs[$element->id]['descendants'] = $element->condition->descendants ?? [];
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    if (!isset($element->wait->minutes)) {
                        throw new ScenarioInvalidDataException("Missing 'minutes' parameter for the Wait node.");
                    }
                    $elementOptions = [
                        'minutes' => $element->wait->minutes
                    ];
                    $elementPairs[$element->id]['descendants'] = $element->wait->descendants ?? [];
                    break;
                case ElementsRepository::ELEMENT_TYPE_GOAL:
                    if (!isset($element->goal->codes)) {
                        throw new ScenarioInvalidDataException("Missing 'codes' parameter for the Goal node.");
                    }
                    if (!isset($element->goal->recheckPeriodMinutes)) {
                        throw new ScenarioInvalidDataException("Missing 'recheckPeriodMinutes' parameter for the Goal node.");
                    }
                    $elementOptions = [
                        'codes' => $element->goal->codes,
                        'recheckPeriodMinutes' => $element->goal->recheckPeriodMinutes,
                    ];
                    if (isset($element->goal->timeoutMinutes)) {
                        $elementOptions['timeoutMinutes'] = $element->goal->timeoutMinutes;
                    }
                    $elementPairs[$element->id]['descendants'] = $element->goal->descendants ?? [];
                    break;
                default:
                    $this->connection->rollback();
                    throw new ScenarioInvalidDataException("Unknown element type [{$element->type}].");
            }

            $elementPairs[$element->id]['type'] = $element->type;
            $elementData['options'] = Json::encode($elementOptions);

            $element = $this->elementsRepository->findByUuid($elementData['uuid']);
            if (!$element) {
                $this->elementsRepository->insert($elementData);
            } else {
                $this->elementsRepository->update($element, $elementData);
            }
        }

        // Delete old elements
        $this->elementsRepository->deleteByUuids(array_keys($oldElements));

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

                switch ($element['type']) {
                    case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    case ElementsRepository::ELEMENT_TYPE_GOAL:
                    case ElementsRepository::ELEMENT_TYPE_CONDITION:
                        $elementElementsData['positive'] = $descendantDef->direction === 'positive' ? true : false;
                        break;
                }

                $link = $this->elementElementsRepository->getLink($parent->id, $descendant->id);
                if (!$link) {
                    $this->elementElementsRepository->insert($elementElementsData);
                } else {
                    $this->elementElementsRepository->update($link, $elementElementsData);
                }
            }
        }

        // TODO: move whole block to triggers repository
        // process triggers (root elements)
        foreach ($data['triggers'] ?? [] as $trigger) {
            if (!in_array($trigger->type, [TriggersRepository::TRIGGER_TYPE_EVENT, TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT], true)) {
                $this->connection->rollback();
                throw new ScenarioInvalidDataException("Unknown trigger type [{$trigger->type}].");
            }
            if (!isset($trigger->event->code)) {
                throw new ScenarioInvalidDataException("Missing 'code' parameter for the Trigger node.");
            }
            if (!$this->eventsStorage->isEventPublic($trigger->event->code)) {
                $this->connection->rollback();
                throw new ScenarioInvalidDataException("Unknown event code [{$trigger->event->code}].");
            }

            $options = [];
            if (isset($trigger->options->minutes)) {
                $options['minutes'] = $trigger->options->minutes;
            }

            $triggerData = [
                'scenario_id' => $scenarioId,
                'event_code' => $trigger->event->code,
                'uuid' => $trigger->id,
                'name' => $trigger->name,
                'type' => $trigger->type,
                'options' => empty($options) ? null : Json::encode($options)
            ];

            unset($oldTriggers[$triggerData['uuid']]);

            $triggerRow = $this->triggersRepository->findByUuid($triggerData['uuid']);
            if (!$triggerRow) {
                $triggerRow = $this->triggersRepository->insert($triggerData);
            } else {
                $this->triggersRepository->update($triggerRow, $triggerData);
            }

            // insert links from triggers
            foreach ($trigger->elements ?? [] as $triggerElementUUID) {
                $triggerElement = $this->elementsRepository->findBy('uuid', $triggerElementUUID);
                if (!$triggerElement) {
                    $this->connection->rollback();
                    throw new \Exception("Unable to find element with uuid [{$triggerElementUUID}]");
                }

                $triggerElementLink = $this->triggerElementsRepository->getLink($triggerRow->id, $triggerElement->id);
                if (!$triggerElementLink) {
                    $this->triggerElementsRepository->addLink($triggerRow->id, $triggerElement->id);
                }
            }
        }

        // Delete old triggers
        $this->triggersRepository->deleteByUuids(array_keys($oldTriggers));

        $this->connection->commit();
        return $scenario;
    }

    final public function getEnabledScenarios()
    {
        return $this->getTable()->where('enabled', true);
    }

    /**
     * Load whole scenario with all triggers and elements.
     *
     * @param int $scenarioID
     *
     * @return array|false if scenario was not found
     * @throws \Nette\Utils\JsonException
     */
    final public function getScenario(int $scenarioID)
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

    final public function setEnabled($scenario, $value = true)
    {
        return $this->update($scenario, [
            'enabled' => $value,
            'modified_at' => new DateTime(),
        ]);
    }

    private function getTriggers(ActiveRow $scenario): array
    {
        $triggers = [];
        $q = $scenario->related('scenarios_triggers')->where('deleted_at IS NULL');
        foreach ($q->fetchAll() as $scenarioTrigger) {
            $trigger = [
                'id' => $scenarioTrigger->uuid,
                'name' => $scenarioTrigger->name,
                'type' => $scenarioTrigger->type,
                'event' => [
                    'code' => $scenarioTrigger->event_code
                ],
                'elements' => [],
            ];

            $options = empty($scenarioTrigger->options) ? [] : Json::decode($scenarioTrigger->options, Json::FORCE_ARRAY);
            if (isset($options['minutes'])) {
                $trigger['options']['minutes'] = $options['minutes'];
            }

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
        $q = $scenario->related('scenarios_elements')->where('deleted_at IS NULL');
        foreach ($q->fetchAll() as $scenarioElement) {
            $element = [
                'id' => $scenarioElement->uuid,
                'name' => $scenarioElement->name,
                'type' => $scenarioElement->type,
            ];

            $descendants = $this->getElementDescendants($scenarioElement);
            $options = Json::decode($scenarioElement->options);

            switch ($scenarioElement->type) {
                case ElementsRepository::ELEMENT_TYPE_EMAIL:
                    if (!isset($options->code)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'code' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'code' => $options->code,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_BANNER:
                    if (!isset($options->id)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'id' in options");
                    }
                    if (!isset($options->expiresInMinutes)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'expiresInMinutes' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'id' => $options->id,
                        'expiresInMinutes' => $options->expiresInMinutes,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    if (!isset($options->code)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'code' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'code' => $options->code,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                    if (!isset($options->conditions)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'conditions' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'conditions' => $options->conditions,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    if (!isset($options->minutes)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'minutes' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'minutes' => $options->minutes,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_GOAL:
                    if (!isset($options->codes)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'codes' in options");
                    }
                    if (!isset($options->recheckPeriodMinutes)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'recheckPeriodMinutes' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'codes' => $options->codes,
                        'recheckPeriodMinutes' => $options->recheckPeriodMinutes,
                        'descendants' => $descendants,
                    ];
                    if (isset($options->timeoutMinutes)) {
                        $element[$scenarioElement->type]['timeoutMinutes'] = $options->timeoutMinutes;
                    }
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
                case ElementsRepository::ELEMENT_TYPE_GOAL:
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                    $d['direction'] = ($descendant->positive == 1 || $descendant->positive === true) ? 'positive' : 'negative';
                    break;
            }
            $descendants[] = $d;
        }
        return $descendants;
    }
}
