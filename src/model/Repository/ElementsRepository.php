<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    const ELEMENT_TYPE_ACTION = 'action';
    const ELEMENT_TYPE_SEGMENT = 'segment';
    const ELEMENT_TYPE_WAIT = 'wait';

    public function removeAllByScenarioID(int $scenarioID)
    {
        foreach ($this->getTable()->where(['scenario_id' => $scenarioID])->fetchAll() as $element) {
            $this->delete($element);
        }
    }
}
