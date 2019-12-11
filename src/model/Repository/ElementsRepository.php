<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Database\Table\Selection;

class ElementsRepository extends Repository
{
    protected $tableName = 'scenarios_elements';

    const ELEMENT_TYPE_EMAIL = 'email';
    const ELEMENT_TYPE_GOAL = 'goal';
    const ELEMENT_TYPE_SEGMENT = 'segment';
    const ELEMENT_TYPE_WAIT = 'wait';
    const ELEMENT_TYPE_BANNER = 'banner';

    public function findByUuid($uuid)
    {
        return $this->findBy('uuid', $uuid);
    }

    public function removeAllByScenarioID(int $scenarioId)
    {
        foreach ($this->allScenarioElements($scenarioId) as $element) {
            $this->delete($element);
        }
    }

    public function allScenarioElements(int $scenarioId): Selection
    {
        return $this->getTable()->where([
            'scenario_id' => $scenarioId
        ]);
    }

    public function findByScenarioIDAndElementUUID(int $scenarioId, string $elementUuid)
    {
        return $this->getTable()->where([
            'scenario_id' => $scenarioId,
            'uuid' => $elementUuid,
        ])->fetch();
    }

    public function deleteByUuids(array $uuids)
    {
        $elements = $this->getTable()->where('uuid IN (?)', $uuids)->fetchAll();
        foreach ($elements as $element) {
            // delete one by one to record changes in audit log
            $this->delete($element);
        }
    }
}
