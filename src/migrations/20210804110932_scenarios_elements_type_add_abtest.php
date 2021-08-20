<?php

use Phinx\Migration\AbstractMigration;

class ScenariosElementsTypeAddABTest extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_elements')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['segment', 'email', 'wait', 'goal', 'banner', 'condition', 'generic', 'push_notification', 'ab_test'],
            ])
            ->update();
    }
}
