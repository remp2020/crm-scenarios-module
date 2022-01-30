<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class JobsRepository extends Repository
{
    const STATE_CREATED = 'created';
    const STATE_SCHEDULED = 'scheduled'; // job is scheduled to run
    const STATE_STARTED = 'started'; // job has already started and is running
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';

    const CONTEXT_HERMES_MESSAGE_TYPE = 'hermes_message_type';

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

    private $elementStatsRepository;

    public function __construct(
        Explorer $database,
        Storage $cacheStorage = null,
        ElementStatsRepository $elementStatsRepository
    ) {
        parent::__construct($database, $cacheStorage);

        $this->elementStatsRepository = $elementStatsRepository;
    }

    /**
     * Adds job associated with a trigger
     * @param            $triggerId
     * @param array      $parameters job parameters
     * @param array|null $context application context
     *
     * @return bool|int|ActiveRow
     * @throws \Nette\Utils\JsonException
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

        $trigger = $this->insert($data);
        return $trigger;
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

        $element = $this->insert($data);
        return $element;
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    final public function startJob(ActiveRow &$row)
    {
        $this->update($row, [
            'state' => self::STATE_STARTED,
            'started_at' => new DateTime(),
        ]);
    }

    final public function finishJob(ActiveRow &$row, bool $recordStats = true)
    {
        $this->update($row, [
            'state' => self::STATE_FINISHED,
            'finished_at' => new DateTime(),
        ]);
        if ($recordStats) {
            $this->elementStatsRepository->add($row->element_id, ElementStatsRepository::STATE_FINISHED);
        }
    }

    final public function scheduleJob(ActiveRow &$row)
    {
        $this->update($row, [
            'state' => self::STATE_SCHEDULED,
        ]);
    }

    final public function getAllJobs()
    {
        return $this->getTable();
    }

    final public function getUnprocessedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_CREATED]);
    }

    final public function getStartedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_STARTED]);
    }

    final public function getScheduledJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_SCHEDULED]);
    }

    final public function getFinishedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_FINISHED]);
    }

    final public function getFailedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_FAILED]);
    }

    final public function getCountForElementsAndState(array $elementIds): array
    {
        $items = $this->getTable()->select('element_id, state, COUNT(*) AS total')
            ->where('element_id', $elementIds)
            ->group('element_id, state');

        $result = [];
        foreach ($items as $item) {
            $result[$item->element_id][$item->state] = (int)$item->total;
        }

        return $result;
    }
}
