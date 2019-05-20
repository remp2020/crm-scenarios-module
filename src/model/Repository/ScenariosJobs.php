<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Caching\IStorage;
use Nette\Database\Connection;
use Nette\Database\Context;

class ScenariosJobs extends Repository
{
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
}
