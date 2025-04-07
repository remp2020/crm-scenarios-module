<?php

namespace Crm\ScenariosModule\Events;

use Exception;

class ScenariosGenericEventsManager
{
    private array $events = [];
    private array $unregisteredEvents = [];

    public function register(string $code, ScenarioGenericEventInterface $event): void
    {
        if (isset($this->events[$code])) {
            throw new Exception("event with code '{$code}' already registered");
        }
        $this->events[$code] = $event;
    }

    public function unregister(string $code): void
    {
        if (!in_array($code, $this->unregisteredEvents, true)) {
            $this->unregisteredEvents[] = $code;
        }
    }

    public function getByCode(string $code): ScenarioGenericEventInterface
    {
        if (!isset($this->events[$code])
            || (isset($this->events[$code]) && in_array($code, $this->unregisteredEvents, true))
        ) {
            throw new Exception("event with code '{$code}' is not registered");
        }
        return $this->events[$code];
    }

    /**
     * @return ScenarioGenericEventInterface[]
     */
    public function getAllRegisteredEvents(): array
    {
        return array_filter($this->events, function ($eventCode) {
            return !in_array($eventCode, $this->unregisteredEvents, true);
        }, ARRAY_FILTER_USE_KEY);
    }
}
