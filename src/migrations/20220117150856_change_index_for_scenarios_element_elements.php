<?php

use Phinx\Migration\AbstractMigration;

class ChangeIndexForScenariosElementElements extends AbstractMigration
{

    public function change()
    {
        $this->table('scenarios_element_elements')
            ->removeIndex(['parent_element_id', 'child_element_id'])
            ->addIndex(['parent_element_id', 'child_element_id', 'position'], ['unique' => true])
            ->update();
    }
}
