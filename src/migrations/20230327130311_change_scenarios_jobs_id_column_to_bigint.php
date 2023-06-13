<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeScenariosJobsIdColumnToBigint extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios_jobs')
            ->changeColumn('id', 'biginteger', ['identity' => true])
            ->update();
    }

    public function down(): void
    {
        $this->output->writeln('Down migration is risky. See migration class for details. Nothing done but not blocking rollback.');
        return;

        // if your IDs have exceeded the integer limit (2^32), keep bigint type
        $this->table('scenarios_jobs')
            ->changeColumn('id', 'integer', ['identity' => true])
            ->update();
    }
}
