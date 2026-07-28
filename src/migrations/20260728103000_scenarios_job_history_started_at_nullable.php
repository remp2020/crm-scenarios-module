<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ScenariosJobHistoryStartedAtNullable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios_job_history')
            ->changeColumn('started_at', 'datetime', ['null' => true])
            ->update();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM scenarios_job_history WHERE started_at IS NULL');

        $this->table('scenarios_job_history')
            ->changeColumn('started_at', 'datetime', ['null' => false])
            ->update();
    }
}
