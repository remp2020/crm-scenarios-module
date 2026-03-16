<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameModifiedAtColumntToUpdatedAtInScenariosTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('scenarios')
            ->renameColumn('modified_at', 'updated_at')
            ->update();
    }
}
