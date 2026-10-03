<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenarioUserJourneyIndexToScenariosJobHistory extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('ALTER TABLE scenarios_job_history
            ADD INDEX (scenario_id, user_id, journey_id),
            ALGORITHM=INPLACE, LOCK=NONE');
    }

    public function down(): void
    {
        $this->table('scenarios_job_history')
            ->removeIndex(['scenario_id', 'user_id', 'journey_id'])
            ->update();
    }
}
