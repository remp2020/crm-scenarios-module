<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;

class ElementElementsRepository extends Repository
{
    protected $tableName = 'scenarios_element_elements';

    public function getLink($parentElementId, $childElementId)
    {
        return $this->getTable()->where([
            'parent_element_id' => $parentElementId,
            'child_element_id' => $childElementId,
        ])->fetch();
    }
}
