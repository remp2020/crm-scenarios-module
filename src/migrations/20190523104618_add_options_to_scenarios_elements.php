<?php


use Phinx\Migration\AbstractMigration;

class AddOptionsToScenariosElements extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_elements')
            ->removeColumn('segment_code')
            ->removeColumn('wait_time')
            ->removeColumn('action_code')
            ->addColumn('options', 'json', ['null' => true])
            ->update();
    }
}
