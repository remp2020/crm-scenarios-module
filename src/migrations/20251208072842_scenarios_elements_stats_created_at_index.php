<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ScenariosElementsStatsCreatedAtIndex extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios_element_stats')
            ->addIndex('created_at')
            ->update();
    }

    public function down(): void
    {
        $this->table('scenarios_element_stats')
            ->removeIndex('created_at')
            ->update();
    }
}
