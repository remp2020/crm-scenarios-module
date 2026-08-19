<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerFrequencyEnum;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class TriggersRepository extends Repository
{
    protected $tableName = 'scenarios_triggers';

    private $triggerElementsRepository;

    private $eventsStorage;

    private $segmentsRepository;

    public const TRIGGER_TYPE_EVENT = 'event';
    public const TRIGGER_TYPE_BEFORE_EVENT = 'before_event';
    public const TRIGGER_TYPE_REPEATING = 'repeating';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Explorer $database,
        TriggerElementsRepository $triggerElementsRepository,
        EventsStorage $eventsStorage,
        SegmentsRepository $segmentsRepository,
        ?Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);

        $this->auditLogRepository = $auditLogRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
        $this->eventsStorage = $eventsStorage;
        $this->segmentsRepository = $segmentsRepository;
    }

    final public function all()
    {
        return $this->scopeNotDeleted();
    }

    final public function findByUuid($uuid)
    {
        return $this->scopeNotDeleted()->where(['uuid' => $uuid])->fetch();
    }

    final public function allScenarioTriggers(int $scenarioId): Selection
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId,
        ]);
    }

    final public function findByType(string $type): Selection
    {
        return $this->scopeNotDeleted()->where([
            'type' => $type,
        ]);
    }

    final public function deleteByUuids(array $uuids)
    {
        foreach ($this->scopeNotDeleted()->where('uuid IN ?', $uuids) as $trigger) {
            $this->delete($trigger);
        }
    }

    final public function delete(ActiveRow &$row)
    {
        if ($row->deleted_at !== null) {
            return true;
        }

        // Soft-delete;
        return $this->update($row, ['deleted_at' => new DateTime()]);
    }

    final public function findByScenarioIdAndTriggerUuid(int $scenarioId, string $triggerUuid)
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId,
            'uuid' => $triggerUuid,
        ])->fetch();
    }

    final public function saveTriggerData(int $scenarioId, \stdClass $trigger): void
    {
        $triggerTypes = [
            self::TRIGGER_TYPE_EVENT,
            self::TRIGGER_TYPE_BEFORE_EVENT,
            self::TRIGGER_TYPE_REPEATING,
        ];
        if (!in_array($trigger->type, $triggerTypes, true)) {
            throw new ScenarioInvalidDataException("Unknown trigger type [{$trigger->type}].");
        }

        $options = [];
        if ($trigger->type === self::TRIGGER_TYPE_REPEATING) {
            // The node is configured with a segment kept in the options, there is no event behind it.
            $eventCode = RepeatingTrigger::EVENT_CODE;
            $options = $this->validateRepeatingOptions($trigger->options ?? null);
        } else {
            if (!isset($trigger->event->code)) {
                throw new ScenarioInvalidDataException("Missing 'code' parameter for the Trigger node.");
            }
            $eventCode = $trigger->event->code;

            if (!$this->eventsStorage->isEventPublic($eventCode)) {
                throw new ScenarioInvalidDataException("Unknown event code [{$eventCode}].");
            }
            if (isset($trigger->options->minutes)) {
                $options['minutes'] = $trigger->options->minutes;
            }
        }

        $triggerData = [
            'scenario_id' => $scenarioId,
            'event_code' => $eventCode,
            'uuid' => $trigger->id,
            'name' => $trigger->name,
            'type' => $trigger->type,
            'options' => empty($options) ? null : Json::encode($options),
        ];

        // soft-deleted triggers are revived rather than inserted again, see ElementsRepository::upsert
        $triggerRow = $this->getTable()->where(['uuid' => $triggerData['uuid']])->fetch();
        if (!$triggerRow) {
            $triggerRow = $this->insert($triggerData);
        } else {
            if ($triggerRow->scenario_id !== $triggerData['scenario_id']) {
                throw new ScenarioInvalidDataException(
                    "Trigger uuid [{$triggerData['uuid']}] already belongs to scenario [{$triggerRow->scenario_id}].",
                );
            }

            if ($triggerRow->deleted_at !== null) {
                $triggerData['deleted_at'] = null;
            }

            $this->update($triggerRow, $triggerData);
        }

        // insert links from triggers
        $this->triggerElementsRepository->addLinksForTrigger($triggerRow, $trigger->elements ?? []);
    }

    private function validateSegment(string $segmentCode): void
    {
        $segment = $this->segmentsRepository->findByCode($segmentCode);
        if (!$segment) {
            throw new ScenarioInvalidDataException("Unknown segment code [{$segmentCode}].");
        }
        if ($segment->table_name !== RepeatingTrigger::SEGMENT_TABLE) {
            throw new ScenarioInvalidDataException(
                "Segment [{$segmentCode}] of a repeating trigger has to be based on the "
                . "'" . RepeatingTrigger::SEGMENT_TABLE . "' table, '{$segment->table_name}' given.",
            );
        }
    }

    private function validateRepeatingOptions(?\stdClass $options): array
    {
        if (!isset($options->segment_code) || !is_string($options->segment_code)) {
            throw new ScenarioInvalidDataException("Missing 'segment_code' option for the repeating Trigger node.");
        }
        $this->validateSegment($options->segment_code);
        $segmentOption = ['segment_code' => $options->segment_code];

        if (!isset($options->frequency) || !is_string($options->frequency)) {
            throw new ScenarioInvalidDataException("Missing 'frequency' option for the repeating Trigger node.");
        }
        $frequency = RepeatingTriggerFrequencyEnum::tryFrom($options->frequency);
        if ($frequency === null) {
            throw new ScenarioInvalidDataException(
                "Unknown repeating trigger frequency [{$options->frequency}], expected one of: "
                . implode(', ', RepeatingTriggerFrequencyEnum::values()) . '.',
            );
        }
        $validatedOptions = $segmentOption + ['frequency' => $frequency->value];

        if (!isset($options->time) || !is_string($options->time)) {
            throw new ScenarioInvalidDataException("Missing 'time' option for the repeating Trigger node.");
        }
        if (!preg_match(RepeatingTrigger::TIME_PATTERN, $options->time)) {
            throw new ScenarioInvalidDataException(
                "Time [{$options->time}] of a repeating trigger is not a valid 'HH:MM' wall-clock time.",
            );
        }
        $validatedOptions['time'] = $options->time;

        $maxDay = $frequency->maxDay();
        if ($maxDay === null) {
            return $validatedOptions;
        }

        if (!isset($options->day)) {
            throw new ScenarioInvalidDataException(
                "Missing 'day' option for a [{$frequency->value}] repeating Trigger node.",
            );
        }
        if (filter_var($options->day, FILTER_VALIDATE_INT) === false) {
            throw new ScenarioInvalidDataException("Day [{$options->day}] of a repeating trigger is not an integer.");
        }
        $day = (int) $options->day;
        if ($day < 1 || $day > $maxDay) {
            throw new ScenarioInvalidDataException(
                "Day [{$day}] of a [{$frequency->value}] repeating trigger is out of range, expected 1-{$maxDay}.",
            );
        }
        $validatedOptions['day'] = $day;

        return $validatedOptions;
    }

    private function scopeNotDeleted()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }
}
