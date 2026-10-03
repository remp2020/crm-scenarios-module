<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ScenariosModule\Engine\JobParamsRedactor;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class JobHistoryRepository extends Repository
{
    protected $tableName = 'scenarios_job_history';

    private const int DEFAULT_RETENTION_MONTHS = 3;
    private const int MAX_RETENTION_MONTHS = 10;
    private const int MAX_SEARCH_LIMIT = 100;
    public const int JOURNEYS_LIMIT = 50;

    private int $retentionMonths = self::DEFAULT_RETENTION_MONTHS;

    public function __construct(
        Explorer $database,
        private readonly UsersRepository $usersRepository,
        private readonly JobParamsRedactor $jobParamsRedactor,
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

    /**
     * Users who passed through given scenario,
     * optionally filtered by email prefix or by exact user ID.
     *
     * If no $emailPrefixOrUserId is provided, recent users are returned.
     *
     * @return Row[]
     */
    final public function usersForScenario(
        int $scenarioId,
        ?string $emailPrefixOrUserId = null,
        int $limit = 30,
    ): array {
        $limit = max(1, min($limit, self::MAX_SEARCH_LIMIT));

        $historySelection = $this->getTable()
            ->select('user_id, COUNT(DISTINCT journey_id) AS journeys_count, MAX(created_at) AS last_job_at')
            ->where('scenario_id', $scenarioId)
            ->where('journey_id IS NOT NULL');

        if (empty($emailPrefixOrUserId)) {
            $userIds = $this->recentUserIds($scenarioId);
            if (!$userIds) {
                return [];
            }
            $historySelection->where('user_id', array_slice($userIds, 0, $limit, true));
        } elseif (ctype_digit($emailPrefixOrUserId)) {
            $historySelection->where('user_id', (int) $emailPrefixOrUserId);
        } else {
            $historySelection->where(
                'user_id IN (SELECT id FROM users WHERE email LIKE ?)',
                // escape LIKE wildcards
                addcslashes($emailPrefixOrUserId, '%_\\') . '%',
            );
        }

        $history = $historySelection
            ->group('user_id')
            ->order('last_job_at DESC')
            ->limit($limit)
            ->fetchAll();

        if (!$history) {
            return [];
        }

        // no foreign key on user_id
        $emails = $this->usersRepository->getTable()
            ->select('id, email')
            ->where('id', array_map(fn ($row) => $row->user_id, $history))
            ->fetchPairs('id', 'email');

        $result = [];
        foreach ($history as $row) {
            $email = $emails[$row->user_id] ?? null;
            if ($email === null) {
                continue;
            }
            $result[] = Row::from([
                'user_id' => $row->user_id,
                'email' => $email,
                'journeys_count' => $row->journeys_count,
                'last_job_at' => $row->last_job_at,
            ]);
        }
        return $result;
    }

    private function recentUserIds(int $scenarioId): array
    {
        return $this->getTable()
            ->select('user_id')
            ->where('scenario_id', $scenarioId)
            ->where('journey_id IS NOT NULL')
            ->order('created_at DESC')
            // optimization - since we are looking up to 30 users overall,
            // 2000 jobs should be enough to find them
            ->limit(2000)
            // keyed by user_id avoids duplicates
            ->fetchPairs('user_id', 'user_id');
    }

    final public function journeysForUser(int $scenarioId, int $userId): array
    {
        return $this->getTable()
            ->select('journey_id, COUNT(*) AS jobs_count, MIN(job_created_at) AS started_at')
            ->select('MAX(COALESCE(finished_at, created_at)) AS ended_at')
            ->where('scenario_id', $scenarioId)
            ->where('user_id', $userId)
            ->where('journey_id IS NOT NULL')
            ->group('journey_id')
            ->order('started_at DESC, journey_id DESC')
            // one extra row tells the caller there are more journeys
            ->limit(self::JOURNEYS_LIMIT + 1)
            ->fetchAll();
    }

    final public function jobsForJourney(int $scenarioId, int $journeyId): array
    {
        return $this->getTable()
            ->select('job_id, trigger_id, element_id, parent_job_id, state, retry_count')
            ->select('parameters, result, context, job_created_at, started_at, finished_at, created_at')
            ->where('scenario_id', $scenarioId)
            ->where('journey_id', $journeyId)
            ->order('job_created_at, job_id')
            ->fetchAll();
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
