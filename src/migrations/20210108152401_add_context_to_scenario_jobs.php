<?php

use Phinx\Migration\AbstractMigration;

class AddContextToScenarioJobs extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_jobs')
            ->addColumn('context', 'json', ['null' => true, 'after' => 'result'])
            ->update();
    }
}
