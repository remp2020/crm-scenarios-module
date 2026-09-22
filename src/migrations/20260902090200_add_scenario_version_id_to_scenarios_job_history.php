<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenarioVersionIdToScenariosJobHistory extends AbstractMigration
{
    public function up(): void
    {
        // no foreign key, the table is partitioned and partitioned InnoDB tables can't have them
        $this->table('scenarios_job_history')
            ->addColumn('scenario_version_id', 'integer', ['null' => true, 'after' => 'parent_job_id'])
            ->addIndex(['scenario_version_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('scenarios_job_history')
            ->removeColumn('scenario_version_id')
            ->update();
    }
}
