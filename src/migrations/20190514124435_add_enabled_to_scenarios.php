<?php

use Phinx\Migration\AbstractMigration;

class AddEnabledToScenarios extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios')
            ->addColumn('enabled', 'boolean', ['null' => false])
            ->save();
    }
}
