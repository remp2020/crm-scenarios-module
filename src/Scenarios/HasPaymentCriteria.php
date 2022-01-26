<?php

namespace Crm\ScenariosModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class HasPaymentCriteria implements ScenariosCriteriaInterface, ScenariosTriggerCriteriaInterface
{
    public const KEY = 'has_payment';

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label())
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        return true;
    }

    public function evaluate($jobParameters, array $paramValues): bool
    {
        $values = $paramValues[self::KEY];

        if ($values->selection === true) {
            return isset($jobParameters->payment_id);
        }

        return !isset($jobParameters->payment_id);
    }

    public function label(): string
    {
        return 'Has payment';
    }
}
