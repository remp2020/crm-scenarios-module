<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
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

    public function dispatch(string $triggerCode, $userId, array $params = [], ?array $context = null)
    {
        foreach ($this->scenariosRepository->getEnabledScenarios() as $scenario) {
            $triggers = $scenario->related('scenarios_triggers')
                ->where([
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'event_code' => $triggerCode,
                    'deleted_at' => null,
                ]);
            foreach ($triggers as $scenarioTrigger) {
                $this->jobsRepository->addTrigger($scenarioTrigger->id, array_merge(['user_id' => $userId], $params), $context);
            }
        }
    }

    public function dispatchTrigger(IRow $triggerRow, int $userId, array $params, ?array $context = null): void
    {
        if ($triggerRow->scenario->enabled) {
            $this->jobsRepository->addTrigger($triggerRow->id, array_merge(['user_id' => $userId], $params), $context);
        }
    }
}
