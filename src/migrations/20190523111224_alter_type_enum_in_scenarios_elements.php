<?php

use Phinx\Migration\AbstractMigration;

class AlterTypeEnumInScenariosElements extends AbstractMigration
{
    public function change()
    {
        // Delete all scenarios and related tables to avoid converting data (scenarios were not in real use at the moment)
        $this->query('SET foreign_key_checks = 0');
        $this->table('scenarios_element_elements')->truncate();
        $this->table('scenarios_trigger_elements')->truncate();
        $this->table('scenarios_elements')->truncate();
        $this->table('scenarios_triggers')->truncate();
        $this->table('scenarios')->truncate();
        $this->query('SET foreign_key_checks = 1');

        $this->table('scenarios_elements')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['segment', 'email', 'wait'],
            ])
            ->update();
    }
}
