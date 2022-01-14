<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Selection;
use Nette\Utils\DateTime;

class TriggerStatsRepository extends Repository
{
    protected $tableName = 'scenarios_trigger_stats';

    final public function add(int $triggerId, string $state, int $count = 1, int $aggregatedMinutes = null, DateTime $createdAt = null)
    {
        return $this->insert([
            'trigger_id' => $triggerId,
            'state' => $state,
            'count' => $count,
            'aggregated_minutes' => $aggregatedMinutes,
            'created_at' => $createdAt ?? new DateTime(),
        ]);
    }

    final public function countsForTriggers(array $triggerIds, DateTime $from): array
    {
        $items = $this->getTable()->select('trigger_id, state, SUM(count) AS total')
            ->where('trigger_id', $triggerIds)
            ->where('created_at >=', $from)
            ->group('trigger_id, state');

        $result = [];
        foreach ($items as $item) {
            $result[$item->trigger_id][$item->state] = (int)$item->total;
        }

        return $result;
    }

    final public function getDataToAggregate(DateTime $from, DateTime $to): Selection
    {
        return $this->getTable()
            ->where('created_at >=', $from)
            ->where('created_at <', $to)
            ->where('aggregated_minutes', null);
    }
}
