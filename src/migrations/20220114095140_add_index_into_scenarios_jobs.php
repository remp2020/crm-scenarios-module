<?php

use Phinx\Migration\AbstractMigration;

class AddIndexIntoScenariosJobs extends AbstractMigration
{
    public function up()
    {
        $this->table('scenarios_jobs')
            ->removeIndex(['element_id'])
            ->addIndex(['element_id', 'state'])
            ->update();
    }

    public function down()
    {
        $this->table('scenarios_jobs')
            ->dropForeignKey('element_id')
            ->update();

        $this->table('scenarios_jobs')
            ->removeIndex(['element_id', 'state'])
            ->update();

        $this->table('scenarios_jobs')
            ->addForeignKey('element_id','scenarios_elements','id')
            ->update();
    }
}
