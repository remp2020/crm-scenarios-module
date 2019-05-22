<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;

class ScenarioStarter
{
    private $jobsRepository;

    private $scenariosRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        ScenariosRepository $scenariosRepository
    )
    {
        $this->jobsRepository = $jobsRepository;
        $this->scenariosRepository = $scenariosRepository;
    }

    public function start(string $triggerCode, $userId)
    {
        foreach ($this->scenariosRepository->getEnabledScenarios() as $scenario) {
            foreach ($scenario->related('scenarios_triggers')->fetchAll() as $scenarioTrigger) {
                if ($scenarioTrigger->event_code === $triggerCode) {
                    $this->jobsRepository->addTrigger($scenarioTrigger, [
                        'user_id' => $userId
                    ]);
                }
            }
        }
    }
}
