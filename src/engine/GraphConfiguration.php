<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Repository\ElementElementsRepository;
use Crm\ScenariosModule\Repository\TriggerElementsRepository;

class GraphConfiguration
{
    private $lastLoadTime = null;

    private $elementElementsRepository;

    private $triggerElementsRepository;

    private $triggerElements = [];

    private $elementElements = [];

    public function __construct(
        ElementElementsRepository $elementElementsRepository,
        TriggerElementsRepository $triggerElementsRepository
    )
    {
        $this->elementElementsRepository = $elementElementsRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
    }

    public function reloadIfOutdated(int $expirationInSeconds = 60)
    {
        if (!$this->lastLoadTime || (time() - $this->lastLoadTime) > $expirationInSeconds) {
            $this->reload();
        }
    }

    public function reload()
    {
        $this->triggerElements = [];
        $this->elementElements = [];

        foreach ($this->triggerElementsRepository->getTable()->fetchAll() as $triggerElements) {
            $triggerElements[$triggerElements->trigger_id] = $triggerElements->element_id;
        }

        $this->lastLoadTime = time();
    }

    public function triggerElements($triggerId): array
    {
        if (array_key_exists($triggerId, $this->triggerElements)) {
            return $triggerId->triggerElements[$triggerId];
        }
        return [];
    }
}
