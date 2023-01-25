<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSoftDeleteToScenariosJobs extends AbstractMigration
{
    public function change(): void
    {
        $this->table('scenarios_jobs')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'updated_at'])
            ->addIndex('deleted_at')
            ->update();
    }
}
