<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    public function removeAllByScenario(int $scenarioID)
    {
        foreach ($this->getTable()->where(['scenario_id' => $scenarioID])->fetchAll() as $element) {
            $this->delete($element);
        }
    }
}
