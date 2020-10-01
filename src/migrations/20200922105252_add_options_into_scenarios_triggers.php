<?php

use Phinx\Migration\AbstractMigration;

class AddOptionsIntoScenariosTriggers extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_triggers')
            ->addColumn('type', 'enum', [
                'null' => false,
                'values' => ['event', 'before_event'],
                'after' => 'name'
            ])
            ->addColumn('options', 'json', ['null' => true, 'after' => 'type'])
            ->update();
    }
}
