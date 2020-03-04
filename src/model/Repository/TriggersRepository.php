<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Caching\IStorage;
use Nette\Database\Context;

class TriggersRepository extends Repository
{
    protected $tableName = 'scenarios_triggers';

    const TRIGGER_TYPE_EVENT = 'event';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Context $database,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
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

    final public function deleteByUuids(array $uuids)
    {
        foreach ($this->getTable()->where('uuid IN ?', $uuids) as $trigger) {
            $this->delete($trigger);
        }
    }

    final public function delete(IRow &$row)
    {
        // Soft-delete
        $this->update($row, ['deleted_at' => new DateTime()]);
    }

    final public function findByScenarioIdAndTriggerUuid(int $scenarioId, string $triggerUuid)
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId,
            'uuid' => $triggerUuid,
        ])->fetch();
    }

    private function scopeNotDeleted()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }
}
