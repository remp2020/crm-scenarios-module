<?php

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Repositories\AuditLogRepository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

class ElementElementsRepository extends Repository
{
    protected $tableName = 'scenarios_element_elements';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Explorer $database,
        Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function getLink(int $parentElementId, int $childElementId, int $position = 0)
    {
        return $this->getTable()->where([
            'parent_element_id' => $parentElementId,
            'child_element_id' => $childElementId,
            'position' => $position,
        ])->fetch();
    }

    final public function deleteLinksForElements(array $elementIds)
    {
        $q = $this->getTable()->where('parent_element_id IN (?) OR child_element_id IN (?)', $elementIds, $elementIds);
        foreach ($q as $link) {
            $this->delete($link);
        }
    }

    final public function upsert(ActiveRow $parent, ActiveRow $descendant, object $descendantDef)
    {
        $elementElementsData = [
            'parent_element_id' => $parent->id,
            'child_element_id' => $descendant->id,
        ];

        switch ($parent->type) {
            case ElementsRepository::ELEMENT_TYPE_SEGMENT:
            case ElementsRepository::ELEMENT_TYPE_GOAL:
            case ElementsRepository::ELEMENT_TYPE_CONDITION:
                $elementElementsData['positive'] = $descendantDef->direction === 'positive';
                break;
            case ElementsRepository::ELEMENT_TYPE_ABTEST:
                $elementElementsData['positive'] = $descendantDef->direction === 'positive';
                $elementElementsData['position'] = $descendantDef->position;
        }

        $link = $this->getLink($parent->id, $descendant->id, $descendantDef->position ?? 0);
        if (!$link) {
            $this->insert($elementElementsData);
        } else {
            $this->update($link, $elementElementsData);
        }
    }
}
