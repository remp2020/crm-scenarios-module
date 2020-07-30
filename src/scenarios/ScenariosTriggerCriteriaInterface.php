<?php

namespace Crm\ScenariosModule\Scenarios;

interface ScenariosTriggerCriteriaInterface
{
    public function evaluate($parameters, $selection): bool;
}
