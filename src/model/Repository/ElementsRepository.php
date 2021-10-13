<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Repository\AuditLogRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Utils\DateTime;
use Nette\Caching\IStorage;
use Nette\Database\Context;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    const ELEMENT_TYPE_EMAIL = 'email';
    const ELEMENT_TYPE_GOAL = 'goal';
    const ELEMENT_TYPE_SEGMENT = 'segment';
    const ELEMENT_TYPE_CONDITION = 'condition';
    const ELEMENT_TYPE_WAIT = 'wait';
    const ELEMENT_TYPE_BANNER = 'banner';
    const ELEMENT_TYPE_GENERIC = 'generic';
    const ELEMENT_TYPE_PUSH_NOTIFICATION = 'push_notification';

    public function __construct(
        AuditLogRepository $auditLogRepository,
        Context $database,
        IStorage $cacheStorage = null
    ) {
        parent::__construct($database, $cacheStorage);
        $this->auditLogRepository = $auditLogRepository;
    }

    final public function all()
    {
        return $this->scopeNotDeleted();
    }

    final public function findByUuid($uuid)
    {

        return $this->scopeNotDeleted()->where(['uuid' => $uuid])->fetch();
    }

    final public function removeAllByScenarioID(int $scenarioId)
    {
        foreach ($this->allScenarioElements($scenarioId) as $element) {
            $this->delete($element);
        }
    }

    final public function allScenarioElements(int $scenarioId): Selection
    {
        return $this->scopeNotDeleted()->where([
            'scenario_id' => $scenarioId
        ]);
    }

    final public function findByScenarioIDAndElementUUID(int $scenarioId, string $elementUuid)
    {
        return $this->allScenarioElements($scenarioId)
            ->where(['uuid' => $elementUuid])
            ->fetch();
    }

    final public function delete(IRow &$row)
    {
        // Soft-delete
        return $this->update($row, ['deleted_at' => new DateTime()]);
    }

    final public function deleteByUuids(array $uuids)
    {
        $elements = $this->scopeNotDeleted()->where('uuid IN (?)', $uuids)->fetchAll();
        foreach ($elements as $element) {
            $this->delete($element);
        }
    }

    private function scopeNotDeleted()
    {
        return $this->getTable()->where('deleted_at IS NULL');
    }
}
