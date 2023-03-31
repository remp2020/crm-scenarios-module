<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeScenariosJobsIdColumnToBigint extends AbstractMigration
{
    public function change(): void
    {
        $this->table('scenarios_jobs')
            ->changeColumn('id', 'biginteger', ['identity' => true])
            ->update();
    }
}
