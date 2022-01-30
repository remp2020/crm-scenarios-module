<?php

use Phinx\Migration\AbstractMigration;

class MigrateScenariosToUserRegisteredEvent extends AbstractMigration
{
    public function up()
    {
                $sql = <<<SQL
UPDATE scenarios_triggers SET event_code = 'user_registered' WHERE event_code = 'user_created';
SQL;

        $this->execute($sql);
    }

        public function down()
    {
                $sql = <<<SQL
UPDATE scenarios_triggers SET event_code = 'user_created' WHERE event_code = 'user_registered';
SQL;

        $this->execute($sql);
    }
}
