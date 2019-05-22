<?php

use Phinx\Migration\AbstractMigration;

class ScenariosJobs extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_jobs')
            ->addColumn('scenario_id', 'integer', ['null' => false])
            ->addColumn('trigger_id', 'integer', ['null' => true])
            ->addColumn('element_id', 'integer', ['null' => true])

            ->addColumn('state', 'string', ['null' => false])
            ->addColumn('parameters', 'json', ['null' => true])
            ->addColumn('result', 'json', ['null' => true])

            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])

            ->addForeignKey('scenario_id', 'scenarios', 'id')
            ->addForeignKey('trigger_id','scenarios_triggers','id')
            ->addForeignKey('element_id','scenarios_elements','id')
            ->create();
    }
}
