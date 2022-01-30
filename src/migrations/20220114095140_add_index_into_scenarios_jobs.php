<?php

use Phinx\Migration\AbstractMigration;

class AddIndexIntoScenariosJobs extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_jobs')
            ->removeIndex(['element_id'])
            ->addIndex(['element_id', 'state'])
            ->update();
    }
}
