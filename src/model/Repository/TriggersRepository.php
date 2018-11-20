<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class TriggersRepository extends Repository
{
    protected $tableName = 'scenarios_triggers';

    public function removeAllByScenarioID(int $scenarioID)
    {
        foreach ($this->getTable()->where(['scenario_id' => $scenarioID])->fetchAll() as $trigger) {
            $this->delete($trigger);
        }
    }
}
