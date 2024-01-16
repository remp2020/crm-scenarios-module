<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Crm\ScenariosModule\Events\AbTestElementUpdatedEvent;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    private $emitter;

    const ELEMENT_TYPE_EMAIL = 'email';
    const ELEMENT_TYPE_GOAL = 'goal';
    const ELEMENT_TYPE_SEGMENT = 'segment';
    const ELEMENT_TYPE_CONDITION = 'condition';
    const ELEMENT_TYPE_WAIT = 'wait';
    const ELEMENT_TYPE_BANNER = 'banner';
    const ELEMENT_TYPE_GENERIC = 'generic';
    const ELEMENT_TYPE_PUSH_NOTIFICATION = 'push_notification';
    const ELEMENT_TYPE_ABTEST = 'ab_test';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Explorer $database,
        Emitter $emitter,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);

        $this->auditLogRepository = $auditLogRepository;
        $this->emitter = $emitter;
    }

    final public function all()
    {
        return $this->scopeNotDeleted();
    }

    final public function findByUuid($uuid)
    {

        return $this->scopeNotDeleted()->where(['uuid' => $uuid])->fetch();
    }

    final public function removeAllByScenarioID(int $scenarioId)
    {
        foreach ($this->allScenarioElements($scenarioId) as $element) {
            $this->delete($element);
        }
    }

    final public function allScenarioElements(int $scenarioId): Selection
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId
        ]);
    }

    final public function findByScenarioIDAndElementUUID(int $scenarioId, string $elementUuid)
    {
        return $this->allScenarioElements($scenarioId)
            ->where(['uuid' => $elementUuid])
            ->fetch();
    }

    final public function delete(ActiveRow &$row)
    {
        // Soft-delete
        return $this->update($row, ['deleted_at' => new DateTime()]);
    }

    final public function deleteByUuids(array $uuids)
    {
        $elements = $this->scopeNotDeleted()->where('uuid IN (?)', $uuids)->fetchAll();
        foreach ($elements as $element) {
            $this->delete($element);
        }
    }

    final public function saveElementData(int $scenarioId, \stdClass $element, array &$elementPairs): void
    {
        $elementData = [
            'scenario_id' => $scenarioId,
            'uuid' => $element->id,
            'name' => $element->name,
            'type' => $element->type,
        ];

        switch ($element->type) {
            case self::ELEMENT_TYPE_EMAIL:
                if (!isset($element->email->code)) {
                    throw new ScenarioInvalidDataException("Missing 'code' parameter for the Email node.");
                }
                $elementOptions = [
                    'code' => $element->email->code
                ];
                $elementPairs[$element->id]['descendants'] = $element->email->descendants ?? [];
                break;
            case self::ELEMENT_TYPE_BANNER:
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
            case self::ELEMENT_TYPE_GENERIC:
                if (!isset($element->generic->code)) {
                    throw new ScenarioInvalidDataException("Missing 'code' parameter for the Generic node.");
                }
                $elementOptions = [
                    'code' => $element->generic->code,
                    'options' => $element->generic->options ?? [],
                ];
                $elementPairs[$element->id]['descendants'] = $element->generic->descendants ?? [];
                break;
            case self::ELEMENT_TYPE_SEGMENT:
                if (!isset($element->segment->code)) {
                    throw new ScenarioInvalidDataException("Missing 'code' parameter for the Segment node.");
                }
                $elementOptions = [
                    'code' => $element->segment->code
                ];
                $elementPairs[$element->id]['descendants'] = $element->segment->descendants ?? [];
                break;
            case self::ELEMENT_TYPE_CONDITION:
                if (!isset($element->condition->conditions)) {
                    throw new ScenarioInvalidDataException("Missing 'conditions' parameter for the Condition node.");
                }
                $elementOptions = [
                    'conditions' => $element->condition->conditions
                ];
                $elementPairs[$element->id]['descendants'] = $element->condition->descendants ?? [];
                break;
            case self::ELEMENT_TYPE_WAIT:
                if (!isset($element->wait->minutes)) {
                    throw new ScenarioInvalidDataException("Missing 'minutes' parameter for the Wait node.");
                }
                $elementOptions = [
                    'minutes' => $element->wait->minutes
                ];
                $elementPairs[$element->id]['descendants'] = $element->wait->descendants ?? [];
                break;
            case self::ELEMENT_TYPE_GOAL:
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
            case self::ELEMENT_TYPE_PUSH_NOTIFICATION:
                if (!isset($element->push_notification->template, $element->push_notification->application)) {
                    throw new ScenarioInvalidDataException("Missing 'template' or 'application' parameter for the Push notification node.");
                }
                $elementOptions = [
                    'template' => $element->push_notification->template,
                    'application' => $element->push_notification->application,
                ];
                $elementPairs[$element->id]['descendants'] = $element->push_notification->descendants ?? [];
                break;
            case self::ELEMENT_TYPE_ABTEST:
                if (!isset($element->ab_test->variants)) {
                    throw new ScenarioInvalidDataException("Missing 'variants' parameter for the AB test node.");
                }
                $segments = [];

                /**
                 * @var int $index
                 * @var \stdClass $variant
                 */
                foreach ($element->ab_test->variants as $index => $variant) {
                    if (isset($variant->segment)) {
                        $segments[] = [
                            'id' => $variant->segment->id ?? null,
                            'uuid' => $variant->code,
                            'name' => $variant->segment->name,
                        ];
                        $element->ab_test->variants[$index]->segment_id = $variant->segment->id ?? null;
                        unset($variant->segment);
                    }
                }

                $elementOptions = [
                    'variants' => $element->ab_test->variants,
                ];
                $elementPairs[$element->id]['descendants'] = array_filter($element->ab_test->descendants ?? []);
                break;
            default:
                throw new ScenarioInvalidDataException("Unknown element type [{$element->type}].");
        }

        $elementPairs[$element->id]['type'] = $element->type;
        $elementData['options'] = Json::encode($elementOptions);

        $elementRow = $this->upsert($elementData);

        if ($element->type === self::ELEMENT_TYPE_ABTEST) {
            $this->emitter->emit(new AbTestElementUpdatedEvent($elementRow, $segments ?? []));
        }
    }

    final public function upsert(array $elementData)
    {
        $element = $this->findByUuid($elementData['uuid']);
        if (!$element) {
            return $this->insert($elementData);
        }

        $this->update($element, $elementData);
        return $this->find($element->id);
    }

    private function scopeNotDeleted()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }
}
