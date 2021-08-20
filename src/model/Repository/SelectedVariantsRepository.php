<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\IRow;

class SelectedVariantsRepository extends Repository
{
    protected $tableName = 'scenarios_selected_variants';

    public function add(IRow $scenarioElementRow, IRow $userRow, string $variantCode)
    {
        return $this->insert([
            'element_id' => $scenarioElementRow->id,
            'user_id' => $userRow->id,
            'variant_code' => $variantCode,
            'created_at' => new \DateTime(),
        ]);
    }

    public function findByUserAndElement(IRow $userRow, IRow $scenariosElementRow)
    {
        return $this->getTable()->where([
            'user_id' => $userRow->id,
            'element_id' => $scenariosElementRow->id,
        ])->fetch();
    }
}
