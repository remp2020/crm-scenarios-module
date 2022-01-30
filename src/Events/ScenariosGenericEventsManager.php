<?php

namespace Crm\ScenariosModule\Events;

use Exception;

class ScenariosGenericEventsManager
{
    private $events = [];

    public function register(string $code, ScenarioGenericEventInterface $event): void
    {
        if (isset($this->events[$code])) {
            throw new Exception("event with code '{$code}' already registered");
        }
        $this->events[$code] = $event;
    }

    public function getByCode(string $code): ScenarioGenericEventInterface
    {
        if (!isset($this->events[$code])) {
            throw new Exception("event with code '{$code}' is not registered");
        }
        return $this->events[$code];
    }

    /**
     * @return ScenarioGenericEventInterface[]
     */
    public function getAllRegisteredEvents(): array
    {
        return $this->events;
    }
}
