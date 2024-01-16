<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Selection;
use Nette\Utils\DateTime;

class ElementStatsRepository extends Repository
{
    public const STATE_FINISHED = 'finished';
    public const STATE_POSITIVE = 'positive';
    public const STATE_NEGATIVE = 'negative';

    protected $tableName = 'scenarios_element_stats';

    final public function add(int $elementId, string $state, int $count = 1, int $aggregatedMinutes = null, DateTime $createdAt = null)
    {
        return $this->insert([
            'element_id' => $elementId,
            'state' => $state,
            'count' => $count,
            'aggregated_minutes' => $aggregatedMinutes,
            'created_at' => $createdAt ?? new DateTime(),
        ]);
    }

    final public function countsForElements(array $elementIds, DateTime $from): array
    {
        $items = $this->getTable()->select('element_id, state, SUM(count) AS total')
            ->where('element_id', $elementIds)
            ->where('created_at >=', $from)
            ->group('element_id, state');

        $result = [];
        foreach ($items as $item) {
            $result[$item->element_id][$item->state] = (int)$item->total;
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
