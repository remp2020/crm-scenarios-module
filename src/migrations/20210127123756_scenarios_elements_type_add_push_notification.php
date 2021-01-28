<?php

use Phinx\Migration\AbstractMigration;

class ScenariosElementsTypeAddPushNotification extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_elements')
            ->changeColumn('type', 'enum', [
                'null' => false,
                'values' => ['segment', 'email', 'wait', 'goal', 'banner', 'condition', 'generic', 'push_notification'],
            ])
            ->update();
    }
}
