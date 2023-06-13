<?php

use Phinx\Migration\AbstractMigration;

class ChangeIndexForScenariosElementElements extends AbstractMigration
{

    public function up()
    {
        $this->table('scenarios_element_elements')
            ->removeIndex(['parent_element_id', 'child_element_id'])
            ->addIndex(['parent_element_id', 'child_element_id', 'position'], ['unique' => true])
            ->update();
    }

    public function down()
    {
        $this->table('scenarios_element_elements')
            ->removeIndex(['parent_element_id', 'child_element_id', 'position'])
            ->addIndex(['parent_element_id', 'child_element_id'], ['unique' => true])
            ->update();
    }
}
