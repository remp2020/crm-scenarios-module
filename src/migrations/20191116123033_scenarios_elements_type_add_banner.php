<?php

use Phinx\Migration\AbstractMigration;

class ScenariosElementsTypeAddBanner extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_elements')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['segment', 'email', 'wait', 'goal', 'banner'],
            ])
            ->update();
    }
}
