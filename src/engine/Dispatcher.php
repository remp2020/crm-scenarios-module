<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Database\Table\IRow;

class Dispatcher
{
    private $jobsRepository;

    private $scenariosRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        ScenariosRepository $scenariosRepository
    ) {
        $this->jobsRepository = $jobsRepository;
        $this->scenariosRepository = $scenariosRepository;
    }

    public function dispatch(string $triggerCode, $userId, array $params = [])
    {
        foreach ($this->scenariosRepository->getEnabledScenarios() as $scenario) {
            foreach ($scenario->related('scenarios_triggers')->fetchAll() as $scenarioTrigger) {
                if ($scenarioTrigger->event_code === $triggerCode) {
                    $this->jobsRepository->addTrigger($scenarioTrigger->id, array_merge(['user_id' => $userId], $params));
                }
            }
        }
    }

    public function dispatchTrigger(IRow $triggerRow, int $userId, array $params): void
    {
        if ($triggerRow->scenario->enabled) {
            $this->jobsRepository->addTrigger($triggerRow->id, array_merge(['user_id' => $userId], $params));
        }
    }
}
