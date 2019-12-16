<?php

use Phinx\Migration\AbstractMigration;

class RemoveScenarioIdEventCodeIndexFromScenariosTriggers extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_triggers')
            ->dropForeignKey('scenario_id')
            ->removeIndex(['scenario_id', 'event_code'])
            ->update();

        $this->table('scenarios_triggers')
            ->addForeignKey('scenario_id', 'scenarios')
            ->update();
    }
}
