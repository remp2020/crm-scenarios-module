<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddScenariosVersions extends AbstractMigration
{
    public function up(): void
    {
        $this->table('scenarios_versions')
            ->addColumn('scenario_id', 'integer', ['null' => false])
            ->addColumn('version', 'integer', ['null' => false])
            ->addColumn('payload', 'json', ['null' => false])
            ->addColumn('payload_hash', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('created_by', 'integer', ['null' => true])
            ->addColumn('note', 'text', ['null' => true])
            ->addIndex(['scenario_id', 'version'], ['unique' => true])
            ->addIndex(['scenario_id', 'created_at'])
            ->addForeignKey('scenario_id', 'scenarios', 'id')
            ->addForeignKey('created_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->create();
    }

    public function down(): void
    {
        $this->table('scenarios_versions')->drop()->save();
    }
}
