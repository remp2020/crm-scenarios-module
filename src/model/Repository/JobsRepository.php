<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class JobsRepository extends Repository
{
    public const STATE_CREATED = 'created';
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

    public function addTrigger(ActiveRow $trigger, $parameters)
    {
        $id = $this->insert([
            'scenario_id' => $trigger->scenario_id,
            'trigger_id' => $trigger->id,
            'parameters' => Json::encode($parameters),
            'state' => self::STATE_CREATED,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
        ]);
        return $this->find($id);
    }

    public function getUnprocessedJobs()
    {
        return $this->getTable()->where(['state' => self::STATE_CREATED]);
    }
}
