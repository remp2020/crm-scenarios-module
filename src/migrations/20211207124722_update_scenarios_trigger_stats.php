<?php

use Phinx\Migration\AbstractMigration;

class UpdateScenariosTriggerStats extends AbstractMigration
{
    public function up()
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

    public function down()
    {
        $this->table('scenarios_trigger_stats')
            ->truncate();

        $this->table('scenarios_trigger_stats')
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->removeColumn('aggregated_minutes')
            ->removeColumn('created_at')
            ->removeIndex(['trigger_id', 'state'])
            ->addIndex('trigger_id')
            ->update();
    }
}
