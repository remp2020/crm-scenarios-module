<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\ScenariosModule\Seeders\SegmentGroupsSeeder;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Exception;
use Nette\Caching\Storage;
use Nette\Database\Connection;
use Nette\Database\DriverException;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class ScenariosRepository extends Repository
{
    protected $tableName = 'scenarios';

    private $connection;

    private $elementsRepository;

    private $elementElementsRepository;

    private $triggersRepository;

    private $triggerElementsRepository;

    private $segmentsRepository;

    private $jobsRepository;

    public function __construct(
        Explorer $database,
        AuditLogRepository $auditLogRepository,
        Connection $connection,
        ElementsRepository $elementsRepository,
        ElementElementsRepository $elementElementsRepository,
        TriggersRepository $triggersRepository,
        TriggerElementsRepository $triggerElementsRepository,
        SegmentsRepository $segmentsRepository,
        JobsRepository $jobsRepository,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);

        $this->connection = $connection;
        $this->elementsRepository = $elementsRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->triggersRepository = $triggersRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
        $this->auditLogRepository = $auditLogRepository;
        $this->segmentsRepository = $segmentsRepository;
        $this->jobsRepository = $jobsRepository;
    }

    final public function all(?bool $deleted = null)
    {
        $query = $this->getTable()->order('name ASC');

        if (isset($deleted)) {
            if ($deleted) {
                $query->where('deleted_at NOT', null);
            } else {
                $query->where('deleted_at', null);
            }
        }

        return $query;
    }

    /**
     * @param array $data
     * @return false|ActiveRow - Returns false when scenarioID provided for update is not found
     * @throws ScenarioInvalidDataException - when unable to create / update scenario because of invalid data
     * @throws \Exception - when internal error occurs
     */
    final public function createOrUpdate(array $data)
    {
        $inTransaction = false;
        try {
            $this->connection->beginTransaction();
            $inTransaction = true;
        } catch (DriverException $e) {
            // transaction already in progress, ignore exception
        }

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

            // do not allow to edit deleted scenario
            if ($scenario->deleted_at) {
                throw new \Exception("Unable to save deleted scenario. Restore it first.");
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

        try {
            // Delete all links
            $this->triggerElementsRepository->deleteLinksForTriggers(array_values($oldTriggers));
            $this->triggerElementsRepository->deleteLinksForElements(array_values($oldElements));
            $this->elementElementsRepository->deleteLinksForElements(array_values($oldElements));

            // add elements of scenario
            $elementPairs = [];
            foreach ($data['elements'] ?? [] as $element) {
                $this->elementsRepository->saveElementData($scenarioId, $element, $elementPairs);
                unset($oldElements[$element->id]);
            }

            // Delete old elements
            $this->elementsRepository->deleteByUuids(array_keys($oldElements));

            // process elements' descendants
            foreach ($elementPairs as $parentUUID => $element) {
                $parent = $this->elementsRepository->findBy('uuid', $parentUUID);
                if (!$parent) {
                    throw new \Exception("Unable to find element with uuid [{$parentUUID}]");
                }

                foreach ($element['descendants'] as $descendantDef) {
                    $descendant = $this->elementsRepository->findBy('uuid', $descendantDef->uuid);
                    if (!$descendant) {
                        throw new \Exception("Unable to find element with uuid [{$descendantDef->uuid}]");
                    }
                    if ($descendant->id === $parent->id) {
                        throw new \Exception(
                            "Element '{$parent->name}' has link to itself, unable to save the scenario."
                        );
                    }

                    $this->elementElementsRepository->upsert($parent, $descendant, $descendantDef);
                }
            }

            // process triggers (root elements)
            foreach ($data['triggers'] ?? [] as $trigger) {
                $this->triggersRepository->saveTriggerData($scenarioId, $trigger);
                unset($oldTriggers[$trigger->id]);
            }

            // Delete old triggers
            $this->triggersRepository->deleteByUuids(array_keys($oldTriggers));
        } catch (\Exception $exception) {
            if ($inTransaction) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        if ($inTransaction) {
            $this->connection->commit();
        }
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
        if (isset($scenario->deleted_at)) {
            throw new Exception("Can't enable deleted scenario.");
        }

        return $this->update($scenario, [
            'enabled' => $value,
            'modified_at' => new DateTime(),
        ]);
    }

    final public function softDelete($scenario)
    {
        return $this->update($scenario, [
            'enabled' => false,
            'modified_at' => new DateTime(),
            'deleted_at' => new DateTime(),
            'restored_at' => null,
        ]);
    }

    final public function restoreScenario($scenario)
    {
        return $this->update($scenario, [
            'enabled' => false,
            'modified_at' => new DateTime(),
            'deleted_at' => null,
            'restored_at' => new DateTime(),
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
                case ElementsRepository::ELEMENT_TYPE_GENERIC:
                    if (!isset($options->code)) {
                        throw new ScenarioInvalidDataException("Missing 'code' parameter for the Generic node.");
                    }
                    $element[$scenarioElement->type] = [
                        'code' => $options->code,
                        'descendants' => $descendants,
                        'options' => [],
                    ];
                    if (isset($options->options)) {
                        $element[$scenarioElement->type]['options'] = $options->options;
                    }
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
                case ElementsRepository::ELEMENT_TYPE_PUSH_NOTIFICATION:
                    if (!isset($options->template, $options->application)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'template' or 'application' in options");
                    }
                    $element[$scenarioElement->type] = [
                        'template' => $options->template,
                        'application' => $options->application,
                        'descendants' => $descendants,
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_ABTEST:
                    if (!isset($options->variants)) {
                        throw new \Exception("Unable to load element uuid [{$scenarioElement->uuid}] - missing 'variants' in options");
                    }

                    foreach ($options->variants as $index => $variant) {
                        $variant = (array)$variant;
                        if (!array_key_exists('segment_id', $variant)) {
                            continue;
                        }

                        if (is_null($variant['segment_id'])) {
                            $elementRow = $this->elementsRepository->findByUuid($element['id']);
                            $segmentRow = $this->segmentsRepository->findByCode(SegmentGroupsSeeder::getSegmentCode($elementRow, $variant['code']));
                        } else {
                            $segmentRow = $this->segmentsRepository->findById($variant['segment_id']);
                        }

                        if ($segmentRow) {
                            $options->variants[$index]->segment = (object)[
                                'id' => $segmentRow->id,
                                'code' => $segmentRow->code,
                                'name' => $segmentRow->name,
                            ];
                        }
                    }

                    $element[$scenarioElement->type] = [
                        'variants' => $options->variants,
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
                'position' => $descendant->position
            ];
            switch ($element->type) {
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                case ElementsRepository::ELEMENT_TYPE_GOAL:
                case ElementsRepository::ELEMENT_TYPE_ABTEST:
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                    $d['direction'] = ($descendant->positive == 1 || $descendant->positive === true) ? 'positive' : 'negative';
                    $d['position'] = $descendant->position;
                    break;
            }
            $descendants[] = $d;
        }
        return $descendants;
    }
}
