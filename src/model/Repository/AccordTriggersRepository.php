<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class AccordTriggersRepository extends Repository
{
    protected $tableName = 'scenarios_accord_triggers';

    public function removeAllByAccordID(int $accordID)
    {
        foreach ($this->getTable()->where(['accord_id' => $accordID])->fetchAll() as $accordTrigger) {
            $this->delete($accordTrigger);
        }
    }
}
