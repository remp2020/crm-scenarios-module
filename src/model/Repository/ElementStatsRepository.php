<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class ElementStatsRepository extends Repository
{
    protected $tableName = 'scenarios_element_stats';

    public function increment($elementId, $state)
    {
        $now = new DateTime();
        $this->getDatabase()->query(
            'INSERT INTO ' . $this->tableName . ' (`element_id`, `state`, `count`, `updated_at`) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE `count`=VALUES(`count`) + 1, `updated_at` = ?',
            $elementId,
            $state,
            $now,
            $now
        );
    }

    public function countsFor($elementId): array
    {
        $results = [];
        foreach ($this->getTable()->where(['element_id' => $elementId]) as $item) {
            $results[$item->state] = (int) $item->count;
        }
        return $results;
    }
}
