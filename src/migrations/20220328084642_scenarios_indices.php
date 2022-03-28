<?php

use Phinx\Migration\AbstractMigration;

class ScenariosIndices extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_jobs')
            ->addIndex('state')
            ->addIndex('updated_at')
            ->update();
    }
}
