<?php

use Phinx\Migration\AbstractMigration;

class AddTableScenariosGeneratedEvents extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_generated_events')
            ->addColumn('trigger_id', 'integer', ['null' => false])
            ->addColumn('code', 'string', ['null' => false])
            ->addColumn('external_id', 'integer', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('trigger_id', 'scenarios_triggers', 'id')
            ->addIndex(['trigger_id', 'code', 'external_id'], ['unique' => true])
            ->create();
    }
}
