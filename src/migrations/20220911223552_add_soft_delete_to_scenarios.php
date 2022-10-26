<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSoftDeleteToScenarios extends AbstractMigration
{

    public function change(): void
    {
        $this->table('scenarios')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'modified_at'])
            ->addColumn('restored_at', 'datetime', ['null' => true, 'after' => 'deleted_at'])
            ->update();
    }
}
