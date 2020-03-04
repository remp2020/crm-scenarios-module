<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Caching\IStorage;
use Nette\Database\Context;

class ElementElementsRepository extends Repository
{
    protected $tableName = 'scenarios_element_elements';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Context $database,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function getLink($parentElementId, $childElementId)
    {
        return $this->getTable()->where([
            'parent_element_id' => $parentElementId,
            'child_element_id' => $childElementId,
        ])->fetch();
    }

    final public function deleteLinksForElements(array $elementIds)
    {
        $q = $this->getTable()->where('parent_element_id IN (?) OR child_element_id IN (?)', $elementIds, $elementIds);
        foreach ($q as $link) {
            $this->delete($link);
        }
    }
}
