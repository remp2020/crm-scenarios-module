<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenarioVersionIdToScenariosJobs extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios_jobs')
            ->addColumn('scenario_version_id', 'integer', ['null' => true, 'after' => 'parent_job_id'])
            ->addIndex(['scenario_version_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('scenarios_jobs')
            ->removeColumn('scenario_version_id')
            ->update();
    }
}
