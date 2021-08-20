<?php

use Phinx\Migration\AbstractMigration;

class ScenariosElementElementsAddPosition extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_element_elements')
            ->addColumn('position', 'integer', ['default' => 0])
            ->update();
    }
}
