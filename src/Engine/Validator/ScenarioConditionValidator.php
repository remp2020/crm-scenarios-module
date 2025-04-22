<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Engine\Validator;

use Contributte\Translation\Exceptions\InvalidArgument;
use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelRequirementsInterface;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ScenariosModule\Scenarios\ScenariosTriggerCriteriaRequirementsInterface;
use Exception;

class ScenarioConditionValidator
{
    public function __construct(
        private readonly ScenariosCriteriaStorage $criteriaStorage,
        private readonly Translator $translator,
    ) {
    }

    /**
     * @param array $conditionOptions Scenario condition options structure
     * @throws ScenarioElementValidationException
     */
    public function validate(array $conditionOptions, array $triggerOutputParams): void
    {
        if (!array_key_exists('conditions', $conditionOptions)) {
            return; // No conditions/criteria selected
        }

        $conditions = $conditionOptions['conditions'];
        $event = $conditions['event'];

        // Trigger is a special case when each node has it's own input params
        if ($event === 'trigger') {
            $this->validateTriggerCondition($conditionOptions, $triggerOutputParams);
            return;
        }

        $this->validateCondition($conditionOptions, $triggerOutputParams);
    }

    /**
     * @throws ScenarioElementValidationException
     * @throws InvalidArgument
     */
    private function validateCondition(array $conditionOptions, array $triggerOutputParams): void
    {
        $event = $conditionOptions['conditions']['event'];
        $emptyCriterionMessage = $this->translator->translate('scenarios.admin.scenarios.validation_errors.empty_criterion');
        $emptyCriterionValueMessage = $this->translator->translate('scenarios.admin.scenarios.validation_errors.empty_criterion_value');

        if (empty($conditionOptions['conditions']['nodes'])) {
            throw new ScenarioElementValidationException($emptyCriterionMessage);
        }

        foreach ($conditionOptions['conditions']['nodes'] as $criterion) {
            if (empty($criterion['key'])) {
                throw new ScenarioElementValidationException($emptyCriterionMessage);
            }

            $errorMessage = $emptyCriterionValueMessage . ' (' . $criterion['key'] . ')';

            foreach ($criterion['params'] as $param) {
                if (empty($param['values'])) {
                    throw new ScenarioElementValidationException($errorMessage);
                }

                if (!isset($param['values']['selection'])) {
                    throw new ScenarioElementValidationException($errorMessage);
                }

                if (is_array($param['values']['selection']) && empty($param['values']['selection'])) {
                    throw new ScenarioElementValidationException($errorMessage);
                }
            }
        }

        $criteria = $this->criteriaStorage->getConditionModel($event) ?? throw new Exception(sprintf(
            "Unknown condition type '%s'.",
            $event,
        ));
        if (!($criteria instanceof ScenarioConditionModelRequirementsInterface)) {
            return; // due to backward compatibility, just do not validate
        }

        $missingParamsToElement = array_diff($criteria->getInputParams(), $triggerOutputParams);
        if (!empty($missingParamsToElement)) {
            $message = $this->translator->translate('scenarios.admin.scenarios.validation_errors.incompatible_criteria_with_trigger');
            throw new ScenarioElementValidationException($message);
        }
    }

    private function validateTriggerCondition(array $conditionOptions, array $triggerOutputParams): void
    {
        $conditions = $conditionOptions['conditions'];
        $event = $conditions['event'];
        $nodes = $conditions['nodes'];
        $nodeCriteriaKeys = array_map(fn($node) => $node['key'], $nodes);

        foreach ($nodeCriteriaKeys as $nodeCriteriaKey) {
            $criteria = $this->criteriaStorage->getEventCriterion($event, $nodeCriteriaKey) ?? throw new Exception(sprintf(
                "Event creation model for event '%s' and criteria '%s' not found.",
                $event,
                $nodeCriteriaKey,
            ));
            if (!($criteria instanceof ScenariosTriggerCriteriaRequirementsInterface)) {
                continue; // due to backward compatibility, just do not validate
            }

            $missingParamsToElement = array_diff($criteria->getInputParams(), $triggerOutputParams);
            if (!empty($missingParamsToElement)) {
                $message = $this->translator->translate('scenarios.admin.scenarios.validation_errors.incompatible_criteria_with_trigger');
                throw new ScenarioElementValidationException($message);
            }
        }
    }
}
