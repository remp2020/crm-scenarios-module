<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class JobsRepository extends Repository
{
    public const STATE_CREATED = 'created';
    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_STARTED = 'started';
    public const STATE_FINISHED = 'finished';
    public const STATE_FAILED = 'failed';

    protected $tableName = 'scenarios_jobs';

    private $connection;

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        Connection $connection
    ) {
        parent::__construct($database, $cacheStorage);
        $this->connection = $connection;
    }

    public function addTrigger($triggerId, array $parameters)
    {
        return $this->insert([
            'trigger_id' => $triggerId,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'retry_count' => 0,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function addElement($elementId, array $parameters)
    {
        return $this->insert([
            'element_id' => $elementId,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'retry_count' => 0,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
    }

    public function update(IRow &$row, $data)
    {
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

    public function getUnprocessedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_CREATED]);
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
