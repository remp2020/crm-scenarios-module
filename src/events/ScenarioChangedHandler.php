<?php

namespace Crm\ScenariosModule\Events;

use League\Event\AbstractListener;
use League\Event\EventInterface;

class ScenarioChangedHandler extends AbstractListener
{
    public function handle(EventInterface $event)
    {
        if (!($event instanceof ScenarioChangedEvent)) {
            throw new \Exception("Unable to handle, expected ScenarioChangedEvent");
        }
    }
}
