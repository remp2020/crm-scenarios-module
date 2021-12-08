<?php

use Phinx\Migration\AbstractMigration;

class UpdateScenariosTriggerStats extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_trigger_stats')
            ->truncate();

        $this->table('scenarios_trigger_stats')
            ->removeColumn('updated_at')
            ->addColumn('aggregated_minutes', 'integer', ['null' => true, 'after' => 'count'])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->removeIndexByName('trigger_id')
            ->addIndex(['trigger_id', 'state'])
            ->update();
    }
}
