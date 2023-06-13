<?php

use Phinx\Migration\AbstractMigration;

class UpdateScenariosElementStats extends AbstractMigration
{
    public function up()
    {
        $this->table('scenarios_element_stats')
            ->truncate();

        $this->table('scenarios_element_stats')
            ->removeColumn('updated_at')
            ->addColumn('aggregated_minutes', 'integer', ['null' => true, 'after' => 'count'])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->removeIndexByName('element_id')
            ->addIndex(['element_id', 'state'])
            ->update();
    }

    public function down()
    {
        $this->table('scenarios_element_stats')
            ->truncate();

        $this->table('scenarios_element_stats')
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->removeColumn('aggregated_minutes')
            ->removeColumn('created_at')
            ->removeIndex(['element_id', 'state'])
            ->addIndex('element_id')
            ->update();
    }
}
