<?php

use Phinx\Migration\AbstractMigration;

class ScenariosModuleInit extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_events')
            ->addColumn('code', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified_at', 'datetime', ['null' => false])
            ->addIndex('code', ['unique' => true])
            ->create();

        $this->table('scenarios_accords')
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('visual', 'json')
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('modified_at', 'datetime', ['null' => false])
            ->addIndex('name', ['unique' => true])
            ->create();

        $this->table('scenarios_accord_triggers')
            ->addColumn('accord_id', 'integer', ['null' => false])
            ->addColumn('event_id', 'integer', ['null' => false])
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
            ->addForeignKey('accord_id', 'scenarios_accords', 'id')
            ->addForeignKey('event_id', 'scenarios_events', 'id')
            ->addIndex(['accord_id', 'event_id'], ['unique' => true])
            ->addIndex(['accord_id', 'name'], ['unique' => true])
            ->addIndex(['uuid'], ['unique' => true])
            ->create();

        $this->table('scenarios_elements')
            ->addColumn('accord_id', 'integer', ['null' => false])
            ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('type', 'enum', [
                'null' => false,
                'values' => ['segment', 'action', 'wait'],
                ])
            ->addColumn('segment_code', 'string', ['null' => true])
            ->addColumn('wait_time', 'integer', ['null' => true])
            ->addColumn('action_code', 'string', ['null' => true])
            ->addForeignKey('accord_id', 'scenarios_accords', 'id')
            ->addIndex(['accord_id', 'name'], ['unique' => true])
            ->addIndex(['uuid'], ['unique' => true])
            ->create();

        $this->table('scenarios_accord_trigger_elements')
            ->addColumn('accord_trigger_id', 'integer', ['null' => false])
            ->addColumn('element_id', 'integer', ['null' => false])
            ->addForeignKey(
                'accord_trigger_id',
                'scenarios_accord_triggers',
                'id',
                ['delete' => 'CASCADE'])
            ->addForeignKey(
                'element_id',
                'scenarios_elements',
                'id',
                ['delete' => 'CASCADE'])
            ->addIndex(['accord_trigger_id', 'element_id'], ['unique' => true])
            ->create();

        $this->table('scenarios_element_elements', [])
            ->addColumn('parent_element_id', 'integer', ['null' => false])
            ->addColumn('child_element_id', 'integer', ['null' => false])
            ->addColumn('positive', 'boolean', ['default' => true])
            ->addForeignKey(
                'parent_element_id',
                'scenarios_elements',
                'id',
                ['delete' => 'CASCADE'])
            ->addForeignKey(
                'child_element_id',
                'scenarios_elements',
                'id',
                ['delete' => 'CASCADE'])
            ->addIndex(['parent_element_id', 'child_element_id'], ['unique' => true])
            ->create();
    }
}
