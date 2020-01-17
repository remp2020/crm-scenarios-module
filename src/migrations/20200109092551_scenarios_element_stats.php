<?php

use Phinx\Migration\AbstractMigration;

class ScenariosElementStats extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_element_stats')
            ->addColumn('element_id', 'integer', ['null' => false])
            ->addColumn('state', 'string')
            ->addColumn('count', 'integer')
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addForeignKey('element_id', 'scenarios_elements', 'id')
            ->addIndex(['element_id', 'state'], ['unique' => true])
            ->create();
    }
}
