<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class JobsRepository extends Repository
{
    const STATE_CREATED = 'created';
    const STATE_SCHEDULED = 'scheduled'; // job is scheduled to run
    const STATE_STARTED = 'started'; // job has already started and is running
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';

    public static function allStates(): array
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

    private $connection;

    private $triggerStatsRepository;

    private $elementStatsRepository;

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        Connection $connection,
        TriggerStatsRepository $triggerStatsRepository,
        ElementStatsRepository $elementStatsRepository
    ) {
        parent::__construct($database, $cacheStorage);
        $this->connection = $connection;
        $this->triggerStatsRepository = $triggerStatsRepository;
        $this->elementStatsRepository = $elementStatsRepository;
    }

    public function addTrigger($triggerId, array $parameters)
    {
        $trigger = $this->insert([
            'trigger_id' => $triggerId,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'retry_count' => 0,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
        $this->triggerStatsRepository->increment($triggerId, self::STATE_CREATED);
        return $trigger;
    }

    public function addElement($elementId, array $parameters)
    {
        $element = $this->insert([
            'element_id' => $elementId,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'retry_count' => 0,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
        $this->elementStatsRepository->increment($elementId, self::STATE_CREATED);
        return $element;
    }

    public function update(IRow &$row, $data)
    {
        // Update element/triggers stats if job state is changing
        if (array_key_exists('state', $data) && $row->state !== $data['state']) {
            if ($row->trigger_id) {
                $this->triggerStatsRepository->increment($row->trigger_id, $data['state']);
            } else {
                $this->elementStatsRepository->increment($row->element_id, $data['state']);
            }
        }

        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    public function startJob(IRow &$row)
    {
        $this->update($row, [
            'state' => self::STATE_STARTED,
            'started_at' => new DateTime(),
        ]);
    }

    public function finishJob(IRow &$row)
    {
        $this->update($row, [
            'state' => self::STATE_FINISHED,
            'finished_at' => new DateTime(),
        ]);
    }

    public function scheduleJob(IRow &$row)
    {
        $this->update($row, [
            'state' => self::STATE_SCHEDULED,
        ]);
    }

    public function getAllJobs()
    {
        return $this->getTable();
    }

    public function getUnprocessedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_CREATED]);
    }

    public function getStartedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_STARTED]);
    }

    public function getScheduledJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_SCHEDULED]);
    }

    public function getFinishedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_FINISHED]);
    }

    public function getFailedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_FAILED]);
    }
}
