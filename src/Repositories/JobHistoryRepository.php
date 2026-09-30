<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ScenariosModule\Engine\JobParamsRedactor;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class JobHistoryRepository extends Repository
{
    protected $tableName = 'scenarios_job_history';

    private const DEFAULT_RETENTION_MONTHS = 3;
    private const MAX_RETENTION_MONTHS = 10;

    private int $retentionMonths = self::DEFAULT_RETENTION_MONTHS;

    public function __construct(
        private readonly JobParamsRedactor $jobParamsRedactor,
        Explorer $database,
        ?Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
    }

    public function setRetentionMonths(int $retentionMonths): void
    {
        if ($retentionMonths < 1 || $retentionMonths > self::MAX_RETENTION_MONTHS) {
            throw new \InvalidArgumentException(
                'retentionMonths must be between 1 and ' . self::MAX_RETENTION_MONTHS . ", got {$retentionMonths}",
            );
        }
        $this->retentionMonths = $retentionMonths;
    }

    final public function removeOldData(): void
    {
        $partitionsByMonth = $this->existingPartitionsByMonth();
        if (!$partitionsByMonth) {
            return;
        }

        $keepMonths = $this->keepMonths($this->retentionMonths);

        $toTruncate = [];
        foreach ($partitionsByMonth as $month => $partitionName) {
            if (!in_array($month, $keepMonths, true)) {
                $toTruncate[] = $partitionName;
            }
        }

        if (!$toTruncate) {
            return;
        }

        $this->getDatabase()->query(
            sprintf('ALTER TABLE %s TRUNCATE PARTITION %s', $this->tableName, implode(', ', $toTruncate)),
        );
    }

    /**
     * Month numbers (1-12) to keep: the current month plus the `retentionMonths` preceding
     * months (wrapping across the year boundary), so at least that many months are retained.
     *
     * @return int[]
     */
    private function keepMonths(int $retentionMonths): array
    {
        $currentMonth = (int) date('n');
        $months = [];
        for ($i = 0; $i <= $retentionMonths; $i++) {
            // wrap into 1-12: add a large multiple of 12 to keep the modulo non-negative
            $months[] = (($currentMonth - $i - 1 + 1200) % 12) + 1;
        }
        return $months;
    }

    /**
     * @return array<int, string> month number (1-12) => partition name
     */
    private function existingPartitionsByMonth(): array
    {
        $rows = $this->getDatabase()->query(
            'SELECT partition_name AS name, partition_description AS description
             FROM information_schema.PARTITIONS
             WHERE table_schema = DATABASE() AND table_name = ? AND partition_name IS NOT NULL',
            $this->tableName,
        )->fetchAll();

        $byMonth = [];
        foreach ($rows as $row) {
            // LIST(MONTH(created_at)) partition_description is the month number, e.g. "1"
            $byMonth[(int) $row->description] = $row->name;
        }

        return $byMonth;
    }

    final public function add(ActiveRow $job): ActiveRow|int|bool
    {
        $parameters = $job->parameters ? Json::decode($job->parameters, forceArrays: true) : [];

        if (!isset($parameters['user_id'])) {
            throw new \RuntimeException("Unable to log scenario job $job, job has no user_id in parameters [{$job->parameters}]");
        }

        return $this->insert([
            'created_at' => new DateTime(),
            'job_id' => $job->id,
            'scenario_id' => $this->resolveScenarioId($job),
            'trigger_id' => $job->trigger_id,
            'element_id' => $job->element_id,
            'journey_id' => $job->journey_id,
            'parent_job_id' => $job->parent_job_id,
            'scenario_version_id' => $job->scenario_version_id,
            'user_id' => $parameters['user_id'],
            'state' => $job->state,
            'retry_count' => $job->retry_count,
            'parameters' => $job->parameters ? $this->jobParamsRedactor->redactJson($job->parameters) : $job->parameters,
            'result' => $job->result,
            'context' => $job->context ? $this->jobParamsRedactor->redactJson($job->context) : $job->context,
            'job_created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
        ]);
    }

    private function resolveScenarioId(ActiveRow $job): int
    {
        if ($job->trigger_id) {
            return $job->trigger->scenario_id;
        }
        if ($job->element_id) {
            return $job->element->scenario_id;
        }
        throw new \RuntimeException("Job [$job->id] has neither trigger nor element associated with it, one of them is required");
    }
}
