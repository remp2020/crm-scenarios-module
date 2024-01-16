<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Repository;
use Nette\Utils\DateTime;

class GeneratedEventsRepository extends Repository
{
    protected $tableName = 'scenarios_generated_events';

    public function exists(int $triggerId, string $code, int $externalId): bool
    {
        return $this->getTable()->where([
            'trigger_id' => $triggerId,
            'code' => $code,
            'external_id' => $externalId,
        ])->count('*') > 0;
    }

    public function add(int $triggerId, string $code, int $externalId): void
    {
        $this->insert([
            'trigger_id' => $triggerId,
            'code' => $code,
            'external_id' => $externalId,
            'created_at' => new DateTime(),
        ]);
    }
}
