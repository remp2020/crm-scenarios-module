<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenariosJobHistory extends AbstractMigration
{
    public function up(): void
    {
        // Composite primary key (id, created_at): MySQL requires every unique key to
        // contain the partitioning column. The table is append-only and intentionally has
        // no foreign keys (partitioned InnoDB tables can't have them).
        $this->table('scenarios_job_history', [
            'id' => false,
            'primary_key' => ['id', 'created_at'],
        ])
            ->addColumn('id', 'biginteger', ['identity' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('job_id', 'biginteger', ['null' => false])
            ->addColumn('scenario_id', 'integer', ['null' => false])
            ->addColumn('trigger_id', 'integer', ['null' => true])
            ->addColumn('element_id', 'integer', ['null' => true])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('state', 'string', ['null' => false])
            ->addColumn('retry_count', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('parameters', 'json', ['null' => true])
            ->addColumn('result', 'json', ['null' => true])
            ->addColumn('context', 'json', ['null' => true])
            ->addColumn('job_created_at', 'datetime', ['null' => false])
            ->addColumn('started_at', 'datetime', ['null' => false])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addIndex(['user_id'])
            ->addIndex(['scenario_id', 'created_at'])
            ->create();

        $this->execute($this->partitioningSql());
    }

    public function down(): void
    {
        $this->table('scenarios_job_history')->drop()->save();
    }

    private function partitioningSql(): string
    {
        $partitions = [];
        for ($month = 1; $month <= 12; $month++) {
            $partitions[] = sprintf('PARTITION p%02d VALUES IN (%d)', $month, $month);
        }

        return 'ALTER TABLE scenarios_job_history PARTITION BY LIST (MONTH(created_at)) ('
            . implode(', ', $partitions) . ')';
    }
}
