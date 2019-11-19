<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    const ELEMENT_TYPE_EMAIL = 'email';
    const ELEMENT_TYPE_GOAL = 'goal';
    const ELEMENT_TYPE_SEGMENT = 'segment';
    const ELEMENT_TYPE_WAIT = 'wait';
    const ELEMENT_TYPE_BANNER = 'banner';

    public function removeAllByScenarioID(int $scenarioID)
    {
        foreach ($this->getTable()->where(['scenario_id' => $scenarioID])->fetchAll() as $element) {
            $this->delete($element);
        }
    }

    public function findByScenarioIDAndElementUUID(int $scenarioID, string $elementUUID)
    {
        return $this->getTable()->where([
            'scenario_id' => $scenarioID,
            'uuid' => $elementUUID,
        ])->fetch();
    }
}
