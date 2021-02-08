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

    public function addCondition(Selection $selection, $values, IRow $criterionItemRow): bool
    {
        return true;
    }

    public function evaluate($parameters, $values): bool
    {
        if ($values->selection === true) {
            return isset($parameters->payment_id);
        }

        return !isset($parameters->payment_id);
    }

    public function label(): string
    {
        return 'Has payment';
    }
}
