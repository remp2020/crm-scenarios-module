<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Caching\IStorage;
use Nette\Database\Context;

class TriggerElementsRepository extends Repository
{
    protected $tableName = 'scenarios_trigger_elements';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Context $database,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function addLink($triggerId, $elementId)
    {
        return $this->insert([
            'trigger_id' => $triggerId,
            'element_id' => $elementId,
        ]);
    }

    final public function getLink($triggerId, $elementId)
    {
        return $this->getTable()->where([
            'trigger_id' => $triggerId,
            'element_id' => $elementId,
        ])->fetch();
    }

    final public function deleteLinksForTriggers(array $triggerIds)
    {
        foreach ($this->getTable()->where('trigger_id IN ?', $triggerIds) as $link) {
            $this->delete($link);
        }
    }

    final public function deleteLinksForElements(array $elementIds)
    {
        foreach ($this->getTable()->where('element_id IN ?', $elementIds) as $link) {
            $this->delete($link);
        }
    }
}
