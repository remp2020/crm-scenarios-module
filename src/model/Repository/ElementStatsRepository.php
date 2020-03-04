<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class ElementStatsRepository extends Repository
{
    protected $tableName = 'scenarios_element_stats';

    final public function increment($elementId, $state)
    {
        $now = new DateTime();
        $this->getDatabase()->query(
            'INSERT INTO ' . $this->tableName . ' (`element_id`, `state`, `count`, `updated_at`) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE `count`= `count` + 1, `updated_at` = ?',
            $elementId,
            $state,
            $now,
            $now
        );
    }

    final public function countsFor($elementId): array
    {
        $results = [];
        foreach ($this->getTable()->where(['element_id' => $elementId]) as $item) {
            $results[$item->state] = (int) $item->count;
        }
        return $results;
    }
}
