<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddJourneyIdToScenariosJobHistory extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios_job_history')
            ->addColumn('journey_id', 'biginteger', ['null' => true, 'after' => 'element_id'])
            ->addColumn('parent_job_id', 'biginteger', ['null' => true, 'after' => 'journey_id'])
            ->addIndex(['journey_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('scenarios_job_history')
            ->removeColumn('journey_id')
            ->removeColumn('parent_job_id')
            ->update();
    }
}
