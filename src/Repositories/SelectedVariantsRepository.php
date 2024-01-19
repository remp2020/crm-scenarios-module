<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Database\Table\ActiveRow;

class SelectedVariantsRepository extends Repository
{
    protected $tableName = 'scenarios_selected_variants';

    public function add(ActiveRow $scenarioElementRow, ActiveRow $userRow, string $variantCode)
    {
        return $this->insert([
            'element_id' => $scenarioElementRow->id,
            'user_id' => $userRow->id,
            'variant_code' => $variantCode,
            'created_at' => new \DateTime(),
        ]);
    }

    public function findByUserAndElement(ActiveRow $userRow, ActiveRow $scenariosElementRow)
    {
        return $this->getTable()->where([
            'user_id' => $userRow->id,
            'element_id' => $scenariosElementRow->id,
        ])->fetch();
    }
}
