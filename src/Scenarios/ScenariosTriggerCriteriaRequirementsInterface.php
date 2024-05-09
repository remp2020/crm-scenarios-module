<?php

namespace Crm\ScenariosModule\Scenarios;

// In the next major release this interface will be merged into Crm\ScenariosModule\Scenarios\ScenariosTriggerCriteriaInterface
interface ScenariosTriggerCriteriaRequirementsInterface
{
    /**
     * This method should return a list of parameters that are required for this trigger criteria to work.
     *
     * @return string[]
     */
    public function getInputParams(): array;
}
