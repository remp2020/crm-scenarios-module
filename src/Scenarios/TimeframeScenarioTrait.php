<?php

declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios;

use Nette\Utils\DateTime;
use Nette\Utils\Json;

trait TimeframeScenarioTrait
{
    /**
     * @param string[] $units
     * @param string[] $operators
     * @return null|array{limit: DateTime|false, operator: string}
     */
    protected function getTimeframe(array $paramValues, array $units, array $operators, string $timeFrameKey): ?array
    {
        if (isset(
            $paramValues[$timeFrameKey],
            $paramValues[$timeFrameKey]->operator,
            $paramValues[$timeFrameKey]->unit,
            $paramValues[$timeFrameKey]->selection
        )) {
            $timeframeOperator = array_search($paramValues[$timeFrameKey]->operator, $operators, true);
            if ($timeframeOperator === false) {
                throw new \Exception("Timeframe operator [{$timeframeOperator}] is not a valid operator out of: " . Json::encode(array_values($operators)));
            }
            $timeframeUnit = $paramValues[$timeFrameKey]->unit;
            if (!in_array($timeframeUnit, $units, true)) {
                throw new \Exception("Timeframe unit [{$timeframeUnit}] is not a valid unit out of: " . Json::encode($units));
            }
            $timeframeValue = $paramValues[$timeFrameKey]->selection;
            if (filter_var($timeframeValue, FILTER_VALIDATE_INT, array("options" => array("min_range" => 0))) === false) {
                throw new \Exception("Timeframe value [{$timeframeValue}] is not a valid value. It has to be positive integer.");
            }

            $limit = (new DateTime())->modify('-' . $timeframeValue . $timeframeUnit);
            $operator = $operators[$timeframeOperator];

            return [
                'limit' => $limit,
                'operator' => $operator,
            ];
        }

        return null;
    }
}
