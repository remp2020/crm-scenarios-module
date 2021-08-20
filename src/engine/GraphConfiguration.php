<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Repository\ElementElementsRepository;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\TriggerElementsRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;

class GraphConfiguration
{
    private const POSITIVE_PATH = 'positive';
    private const NEGATIVE_PATH = 'negative';

    private $elementElementsRepository;

    private $triggerElementsRepository;

    private $elementsRepository;

    private $triggersRepository;

    private $triggerElements = [];

    private $elementElements = [];
    
    private $lastReloadTimestamp;

    public function __construct(
        ElementsRepository $elementsRepository,
        TriggersRepository $triggersRepository,
        ElementElementsRepository $elementElementsRepository,
        TriggerElementsRepository $triggerElementsRepository
    ) {
        $this->elementsRepository = $elementsRepository;
        $this->triggersRepository = $triggersRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->triggerElementsRepository = $triggerElementsRepository;
    }

    /**
     * Reload graph configuration
     *
     * @param int $minReloadDelay in seconds
     */
    public function reload(int $minReloadDelay = 0)
    {
        $currentTime = time();
        if ($this->lastReloadTimestamp && ($this->lastReloadTimestamp + $minReloadDelay) > $currentTime) {
            return;
        }
        $this->lastReloadTimestamp = $currentTime;

        // Reload triggers and their links
        $this->triggerElements = [];

        foreach ($this->triggersRepository->all() as $trigger) {
            $this->triggerElements[$trigger->id] = [];
        }

        foreach ($this->triggerElementsRepository->getTable()->fetchAll() as $te) {
            if (array_key_exists($te->trigger_id, $this->triggerElements)) {
                $this->triggerElements[$te->trigger_id][] = $te->element_id;
            }
        }

        // Reload elements and their links
        $this->elementElements = [];

        foreach ($this->elementsRepository->all() as $element) {
            $this->elementElements[$element->id] = [
                self::POSITIVE_PATH => [],
                self::NEGATIVE_PATH => [],
            ];
        }

        foreach ($this->elementElementsRepository->getTable()->fetchAll() as $ee) {
            if (array_key_exists($ee->parent_element_id, $this->elementElements)) {
                $direction = $ee->positive ? self::POSITIVE_PATH : self::NEGATIVE_PATH;
                $this->elementElements[$ee->parent_element_id][$direction][$ee->position][] = $ee->child_element_id;
            }
        }
    }

    /**
     * @param $triggerId
     *
     * @return array
     * @throws NodeDeletedException
     */
    public function triggerDescendants($triggerId): array
    {
        if (array_key_exists($triggerId, $this->triggerElements)) {
            return $this->triggerElements[$triggerId];
        }
        throw new NodeDeletedException("Trigger with ID $triggerId is missing, probably deleted");
    }

    /**
     * @param      $elementId
     * @param bool $positive direction from element (some element have 'negative' direction, such as Segment)
     * @param int $position if element supports more than one positive/negative port, like AB Test
     *
     * @return array
     * @throws NodeDeletedException
     */
    public function elementDescendants($elementId, bool $positive = true, int $position = 0): array
    {
        if (array_key_exists($elementId, $this->elementElements)) {
            return $this->elementElements[$elementId][$positive ? self::POSITIVE_PATH : self::NEGATIVE_PATH][$position] ?? [];
        }
        throw new NodeDeletedException("Element with ID $elementId is missing, probably deleted");
    }
}
