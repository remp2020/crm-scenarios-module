<?php

namespace Crm\ScenariosModule\Tests;

use League\Event\AbstractListener;
use League\Event\EventInterface;

class TriggerGenericActionEventHandler extends AbstractListener
{
    public $eventWasTriggered = false;

    public function handle(EventInterface $event)
    {
        $this->eventWasTriggered = true;
    }
}
