<?php

use Phinx\Migration\AbstractMigration;

class ScenariosSelectedVariants extends AbstractMigration
{
    public function change()
    {
        $this->table('scenarios_selected_variants')
            ->addColumn('element_id', 'integer', ['null' => false])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('variant_code', 'string', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('element_id', 'scenarios_elements', 'id')
            ->addForeignKey('user_id', 'users', 'id')
            ->addIndex(['user_id', 'element_id', 'variant_code'], ['unique' => true])
            ->create();
    }
}
