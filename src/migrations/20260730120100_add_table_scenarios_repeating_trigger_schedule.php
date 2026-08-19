<?php

use Phinx\Migration\AbstractMigration;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerScheduleStateEnum;

class AddTableScenariosRepeatingTriggerSchedule extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_repeating_trigger_schedule')
            ->addColumn('trigger_id', 'integer', ['null' => false])
            ->addColumn('occurred_at', 'datetime', ['null' => false])
            ->addColumn('state', 'enum', ['null' => false, 'values' => RepeatingTriggerScheduleStateEnum::values()])
            ->addColumn('jobs_created', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addForeignKey('trigger_id', 'scenarios_triggers', 'id', ['delete' => 'CASCADE'])
            ->addIndex(['trigger_id', 'occurred_at'], ['unique' => true])
            ->create();
    }
}
