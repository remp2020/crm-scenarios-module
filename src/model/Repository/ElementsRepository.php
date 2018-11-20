<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    public function removeAllByAccord(int $accordID)
    {
        foreach ($this->getTable()->where(['accord_id' => $accordID])->fetchAll() as $element) {
            $this->delete($element);
        }
    }
}
