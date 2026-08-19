<?php

use Phinx\Migration\AbstractMigration;

class ScenariosTriggersTypeAddRepeating extends AbstractMigration
{
    public function up()
    {
        $this->table('scenarios_triggers')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['event', 'before_event', 'repeating'],
            ])
            ->update();
    }

    public function down()
    {
        $this->execute("DELETE FROM scenarios_triggers WHERE type = 'repeating'");

        $this->table('scenarios_triggers')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['event', 'before_event'],
            ])
            ->update();
    }
}
