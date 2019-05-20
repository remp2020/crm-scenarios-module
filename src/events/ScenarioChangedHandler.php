<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Repository\ScenariosRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class ScenarioChangedHandler extends AbstractListener
{
    private $scenariosRepository;

    public function __construct(ScenariosRepository $scenariosRepository)
    {
        $this->scenariosRepository = $scenariosRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof ScenarioChangedEvent)) {
            throw new \Exception("Unable to handle, expected ScenarioChangedEvent");
        }

        $scenario = $event->getScenario();

        //$this->scenariosRepository->getScenario()

        $triggers = $scenario->related('scenarios_triggers')->fetchAll();
        foreach ($triggers as $trigger) {
            dd($trigger);
        }
    }
}
