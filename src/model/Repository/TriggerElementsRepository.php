<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class TriggerElementsRepository extends Repository
{
    protected $tableName = 'scenarios_trigger_elements';

    public function addLink($triggerId, $elementId)
    {
        return $this->insert([
            'trigger_id' => $triggerId,
            'element_id' => $elementId,
        ]);
    }

    public function getLink($triggerId, $elementId)
    {
        return $this->getTable()->where([
            'trigger_id' => $triggerId,
            'element_id' => $elementId,
        ])->fetch();
    }
}
