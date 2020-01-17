<?php

use Phinx\Migration\AbstractMigration;

class ScenariosTriggerStats extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_trigger_stats')
            ->addColumn('trigger_id', 'integer', ['null' => false])
            ->addColumn('state', 'string')
            ->addColumn('count', 'integer')
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addForeignKey('trigger_id', 'scenarios_triggers', 'id')
            ->addIndex(['trigger_id', 'state'], ['unique' => true])
            ->create();
    }
}
