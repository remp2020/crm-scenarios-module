<?php

use Phinx\Migration\AbstractMigration;

class ScenariosElementsTypeAddGoal extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_elements')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['segment', 'email', 'wait', 'goal'],
            ])
            ->update();
    }
}
