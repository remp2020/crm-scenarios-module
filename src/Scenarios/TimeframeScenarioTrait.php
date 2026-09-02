<?php

declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\ScenariosModule\Events\ConditionCheckException;
use DateMalformedStringException;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

trait TimeframeScenarioTrait
{
    use NowTrait;

    /**
     * @param array $paramValues
     * @param string[] $units
     * @param string[] $operators
     * @param string $timeFrameKey
     * @return null|array{limit: DateTime, operator: string}
     * @throws ConditionCheckException
     * @throws DateMalformedStringException
     * @throws JsonException
     */
    protected function getTimeframe(array $paramValues, array $units, array $operators, string $timeFrameKey): ?array
    {
        $values = $paramValues[$timeFrameKey] ?? null;
        $operator = $values->operator ?? null;
        $unit = $values->unit ?? null;
        $amount = $values->selection ?? null;
        if ($amount === '') {
            $amount = null;
        }

        // When only one operator (or unit) is available, the scenario builder fills it in automatically.
        // It is then saved even for an empty timeframe, so we don't count it as user input.
        $hasUserInput = $amount !== null
            || ($operator !== null && count($operators) > 1)
            || ($unit !== null && count($units) > 1);
        if (!$hasUserInput) {
            return null;
        }

        if ($operator === null || $unit === null || $amount === null) {
            throw new ConditionCheckException("Timeframe [{$timeFrameKey}] is incomplete. Fill in operator, amount and unit, or leave all of them empty to apply no time restriction.");
        }
        if (!in_array($operator, $operators, true)) {
            throw new ConditionCheckException("Timeframe operator [{$operator}] is not a valid operator out of: " . Json::encode(array_values($operators)));
        }
        if (!in_array($unit, $units, true)) {
            throw new ConditionCheckException("Timeframe unit [{$unit}] is not a valid unit out of: " . Json::encode($units));
        }
        if (filter_var($amount, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            throw new ConditionCheckException("Timeframe value [{$amount}] is not a valid value. It has to be positive integer.");
        }

        return [
            'limit' => DateTime::from($this->getNow())->modify("-{$amount} {$unit}"),
            'operator' => $operator,
        ];
    }
}
