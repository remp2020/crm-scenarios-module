<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\Database\Selection;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;

class JobsRepository extends Repository
{
    public const STATE_CREATED = 'created';
    public const STATE_SCHEDULED = 'scheduled'; // job is scheduled to run
    public const STATE_STARTED = 'started'; // job has already started and is running
    public const STATE_FINISHED = 'finished';
    public const STATE_FAILED = 'failed';

    public const CONTEXT_HERMES_MESSAGE_TYPE = 'hermes_message_type';
    public const CONTEXT_BEFORE_EVENT = 'before_event';

    final public static function allStates(): array
    {
        return [
            self::STATE_CREATED,
            self::STATE_SCHEDULED,
            self::STATE_STARTED,
            self::STATE_FINISHED,
            self::STATE_FAILED,
        ];
    }

    protected $tableName = 'scenarios_jobs';

    public function __construct(
        Explorer $database,
        private ElementStatsRepository $elementStatsRepository,
        Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
    }

    /**
     * Adds job associated with a trigger
     * @param            $triggerId
     * @param array      $parameters job parameters
     * @param array|null $context application context
     *
     * @return bool|int|ActiveRow
     * @throws JsonException
     */
    final public function addTrigger($triggerId, array $parameters, ?array $context = null)
    {
        $data = [
            'trigger_id' => $triggerId,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'retry_count' => 0,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ];

        if ($context) {
            $data['context'] = Json::encode($context);
        }

        return $this->insert($data);
    }

    final public function addElement($elementId, array $parameters, ?array $context = null)
    {
        $data = [
            'element_id' => $elementId,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'retry_count' => 0,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ];

        if ($context) {
            $data['context'] = Json::encode($context);
        }

        return $this->insert($data);
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function softDelete(ActiveRow $job): void
    {
        $this->update($job, [
            'deleted_at' => new \DateTime(),
        ]);
    }

    final public function getReadyToProcessJobs(): Selection
    {
        return $this->getAllJobs()
            ->where('state IN (?)', [
                self::STATE_CREATED, self::STATE_FINISHED, self::STATE_FAILED,
            ]);
    }

    final public function getReadyToProcessJobsForEnabledScenarios(): Selection
    {
        return $this->getReadyToProcessJobs()
            ->alias('trigger.scenario', 'triggerScenario')
            ->alias('element.scenario', 'elementScenario')
            ->whereOr([
                'triggerScenario.enabled' => true,
                'elementScenario.enabled' => true,
            ])
            ->whereOr([
                '(scenarios_jobs.trigger_id NOT ? AND triggerScenario.restored_at ?)
                OR scenarios_jobs.created_at > triggerScenario.restored_at' => [null, null],
                '(scenarios_jobs.element_id NOT ? AND elementScenario.restored_at ?)
                OR scenarios_jobs.created_at > elementScenario.restored_at' => [null, null],
            ]);
    }

    final public function deleteUnprocessableJobsForScenarios(): int
    {
        $ids = $this->getReadyToProcessJobs()
            ->alias('trigger.scenario', 'triggerScenario')
            ->alias('element.scenario', 'elementScenario')
            ->whereOr([
                'triggerScenario.deleted_at NOT ? OR scenarios_jobs.created_at < triggerScenario.restored_at' => null,
                'elementScenario.deleted_at NOT ? OR scenarios_jobs.created_at < elementScenario.restored_at' => null,
            ])->fetchPairs(null, 'id');

        return $this->getTable()->where('id', $ids)->delete();
    }

    final public function startJob(ActiveRow $row): ActiveRow
    {
        $this->update($row, [
            'state' => self::STATE_STARTED,
            'started_at' => new DateTime(),
        ]);
        return $row;
    }

    final public function finishJob(ActiveRow $row, bool $recordStats = true): ActiveRow
    {
        $this->update($row, [
            'state' => self::STATE_FINISHED,
            'finished_at' => new DateTime(),
        ]);
        if ($recordStats) {
            if (!isset($row->element_id)) {
                Debugger::log("JobsRepository - trying to finish job with no associated element, row data: " . Json::encode($row->toArray()), Debugger::WARNING);
            } else {
                $this->elementStatsRepository->add($row->element_id, ElementStatsRepository::STATE_FINISHED);
            }
        }
        return $row;
    }

    final public function scheduleJob(ActiveRow $row): ActiveRow
    {
        $this->update($row, [
            'state' => self::STATE_SCHEDULED,
        ]);
        return $row;
    }

    final public function getAllJobs(): Selection
    {
        return $this->getTable()->where('scenarios_jobs.deleted_at IS NULL');
    }

    final public function getDeletedJobs(): Selection
    {
        return $this->getTable()->where('scenarios_jobs.deleted_at IS NOT NULL');
    }

    final public function getUnprocessedJobs(): Selection
    {
        return $this->getAllJobs()->where(['state' => self::STATE_CREATED]);
    }

    final public function getStartedJobs(): Selection
    {
        return $this->getAllJobs()->where(['state' => self::STATE_STARTED]);
    }

    final public function getScheduledJobs(): Selection
    {
        return $this->getAllJobs()->where(['state' => self::STATE_SCHEDULED]);
    }

    final public function getFinishedJobs(): Selection
    {
        return $this->getAllJobs()->where(['state' => self::STATE_FINISHED]);
    }

    final public function getFailedJobs(): Selection
    {
        return $this->getAllJobs()->where(['state' => self::STATE_FAILED]);
    }

    final public function getCountForElementsAndState(array $elementIds): array
    {
        $items = $this->getAllJobs()->select('element_id, state, COUNT(*) AS total')
            ->where('element_id', $elementIds)
            ->group('element_id, state');

        $result = [];
        foreach ($items as $item) {
            $result[$item->element_id][$item->state] = (int)$item->total;
        }

        return $result;
    }
}
