<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class TriggersRepository extends Repository
{
    protected $tableName = 'scenarios_triggers';

    private $triggerElementsRepository;

    private $eventsStorage;

    public const TRIGGER_TYPE_EVENT = 'event';
    public const TRIGGER_TYPE_BEFORE_EVENT = 'before_event';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Context $database,
        TriggerElementsRepository $triggerElementsRepository,
        EventsStorage $eventsStorage,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);

        $this->auditLogRepository = $auditLogRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
        $this->eventsStorage = $eventsStorage;
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
            'scenario_id' => $scenarioId
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
        foreach ($this->getTable()->where('uuid IN ?', $uuids) as $trigger) {
            $this->delete($trigger);
        }
    }

    final public function delete(IRow &$row)
    {
        // Soft-delete
        return $this->update($row, ['deleted_at' => new DateTime()]);
    }

    final public function findByScenarioIdAndTriggerUuid(int $scenarioId, string $triggerUuid)
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId,
            'uuid' => $triggerUuid,
        ])->fetch();
    }

    final public function saveTriggerData(int $scenarioId, object $trigger): void
    {
        if (!in_array($trigger->type, [self::TRIGGER_TYPE_EVENT, self::TRIGGER_TYPE_BEFORE_EVENT], true)) {
            throw new ScenarioInvalidDataException("Unknown trigger type [{$trigger->type}].");
        }
        if (!isset($trigger->event->code)) {
            throw new ScenarioInvalidDataException("Missing 'code' parameter for the Trigger node.");
        }
        if (!$this->eventsStorage->isEventPublic($trigger->event->code)) {
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

        $triggerRow = $this->findByUuid($triggerData['uuid']);
        if (!$triggerRow) {
            $triggerRow = $this->insert($triggerData);
        } else {
            $this->update($triggerRow, $triggerData);
        }

        // insert links from triggers
        $this->triggerElementsRepository->addLinksForTrigger($triggerRow, $trigger->elements ?? []);
    }

    private function scopeNotDeleted()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }
}
