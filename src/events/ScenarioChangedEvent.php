<?php

namespace Crm\ScenariosModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class ScenarioChangedEvent extends AbstractEvent
{
    private $scenario;

    public function __construct(ActiveRow $scenario)
    {
        $this->scenario = $scenario;
    }

    public function getScenario()
    {
        return $this->scenario;
    }
}
