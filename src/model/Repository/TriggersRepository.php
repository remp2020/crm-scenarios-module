<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;

class TriggersRepository extends Repository
{
    protected $tableName = 'scenarios_triggers';

    const TRIGGER_TYPE_EVENT = 'event';

    public function all()
    {
        return $this->scopeNotDeleted();
    }

    public function findByUuid($uuid)
    {
        return $this->scopeNotDeleted()->where(['uuid' => $uuid])->fetch();
    }

    public function allScenarioTriggers(int $scenarioId): Selection
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId
        ]);
    }

    public function deleteByUuids(array $uuids)
    {
        foreach ($this->getTable()->where('uuid IN ?', $uuids) as $trigger) {
            $this->delete($trigger);
        }
    }

    public function delete(IRow &$row)
    {
        // Soft-delete
        $this->update($row, ['deleted_at' => new DateTime()]);
    }

    public function findByScenarioIdAndTriggerUuid(int $scenarioId, string $triggerUuid)
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
