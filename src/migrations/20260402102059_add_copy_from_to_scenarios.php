<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCopyFromToScenarios extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios')
            ->addColumn('copied_from_scenario_id', 'integer', ['null' => true])
            ->addForeignKey('copied_from_scenario_id', 'scenarios')
            ->update();
    }

    public function down(): void
    {
        $this->table('scenarios')
            ->dropForeignKey('copied_from_scenario_id')
            ->removeColumn('copied_from_scenario_id')
            ->update();
    }
}
