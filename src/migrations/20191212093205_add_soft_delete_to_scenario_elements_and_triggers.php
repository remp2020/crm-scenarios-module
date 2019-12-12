<?php

use Phinx\Migration\AbstractMigration;

class AddSoftDeleteToScenarioElementsAndTriggers extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_elements')
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->update();

        $this->table('scenarios_triggers')
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->update();

    }
}
