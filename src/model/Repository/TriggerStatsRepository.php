<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class TriggerStatsRepository extends Repository
{
    protected $tableName = 'scenarios_trigger_stats';

    public function increment($triggerId, $state)
    {
        $now = new DateTime();
        $this->getDatabase()->query(
            'INSERT INTO ' . $this->tableName . ' (`trigger_id`, `state`, `count`, `updated_at`) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE `count`=`count` + 1, `updated_at` = ?',
            $triggerId,
            $state,
            $now,
            $now
        );
    }

    public function countsFor($triggerId): array
    {
        $results = [];
        foreach ($this->getTable()->where(['trigger_id' => $triggerId]) as $item) {
            $results[$item->state] = (int) $item->count;
        }
        return $results;
    }
}
