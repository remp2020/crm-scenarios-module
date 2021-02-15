<?php

namespace Crm\ScenariosModule\Scenarios;

interface ScenariosTriggerCriteriaInterface
{
    public function evaluate($jobParameters, array $paramValues): bool;
}
