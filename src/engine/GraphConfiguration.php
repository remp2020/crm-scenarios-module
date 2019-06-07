<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Repository\ElementElementsRepository;
use Crm\ScenariosModule\Repository\TriggerElementsRepository;

class GraphConfiguration
{
    private const POSITIVE_PATH = 'positive';
    private const NEGATIVE_PATH = 'negative';

    private $lastLoadTime = null;

    private $elementElementsRepository;

    private $triggerElementsRepository;

    private $triggerElements = [];

    private $elementElements = [];

    public function __construct(
        ElementElementsRepository $elementElementsRepository,
        TriggerElementsRepository $triggerElementsRepository
    ) {
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
        $this->lastLoadTime = time();

        $this->triggerElements = [];
        $this->elementElements = [];

        foreach ($this->triggerElementsRepository->getTable()->fetchAll() as $te) {
            if (!array_key_exists($te->trigger_id, $this->triggerElements)) {
                $this->triggerElements[$te->trigger_id] = [];
            }
            $this->triggerElements[$te->trigger_id][] = $te->element_id;
        }

        foreach ($this->elementElementsRepository->getTable()->fetchAll() as $ee) {
            if (!array_key_exists($ee->parent_element_id, $this->elementElements)) {
                $this->elementElements[$ee->parent_element_id] = [
                    self::POSITIVE_PATH => [],
                    self::NEGATIVE_PATH => [],
                ];
            }
            $direction = $ee->positive ? self::POSITIVE_PATH : self::NEGATIVE_PATH;
            $this->elementElements[$ee->parent_element_id][$direction][] = $ee->child_element_id;
        }
    }

    public function triggerDescendants($triggerId): array
    {
        if (array_key_exists($triggerId, $this->triggerElements)) {
            return $this->triggerElements[$triggerId];
        }
        return [];
    }

    public function elementDescendants($elementId, bool $positive = true): array
    {
        if (array_key_exists($elementId, $this->elementElements)) {
            return $this->elementElements[$elementId][$positive ? self::POSITIVE_PATH : self::NEGATIVE_PATH];
        }
        return [];
    }
}
