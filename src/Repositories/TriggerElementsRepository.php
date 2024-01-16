<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class TriggerElementsRepository extends Repository
{
    protected $tableName = 'scenarios_trigger_elements';

    private $elementsRepository;

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Explorer $database,
        ElementsRepository $elementsRepository,
        Storage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);

        $this->auditLogRepository = $auditLogRepository;
        $this->elementsRepository = $elementsRepository;
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

    final public function addLinksForTrigger(ActiveRow $triggerRow, array $elements): void
    {
        foreach ($elements as $triggerElementUUID) {
            $triggerElement = $this->elementsRepository->findBy('uuid', $triggerElementUUID);
            if (!$triggerElement) {
                throw new \Exception("Unable to find element with uuid [{$triggerElementUUID}]");
            }

            $triggerElementLink = $this->getLink($triggerRow->id, $triggerElement->id);
            if (!$triggerElementLink) {
                $this->addLink($triggerRow->id, $triggerElement->id);
            }
        }
    }
}
