<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\Selection;

class TriggersRepository extends Repository
{
    protected $tableName = 'scenarios_triggers';

    const TRIGGER_TYPE_EVENT = 'event';

    public function findByUuid($uuid)
    {
        return $this->findBy('uuid', $uuid);
    }

    public function allScenarioTriggers(int $scenarioId): Selection
    {
        return $this->getTable()->where([
            'scenario_id' => $scenarioId
        ]);
    }

    public function removeAllByScenarioID(int $scenarioID)
    {
        foreach ($this->getTable()->where(['scenario_id' => $scenarioID])->fetchAll() as $trigger) {
            $this->delete($trigger);
        }
    }

    public function deleteByUuids(array $uuids)
    {
        $this->getTable()->where('uuid IN ?', $uuids)->delete();
    }
}
