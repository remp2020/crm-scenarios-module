<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Engine\Validator;

use Contributte\Translation\Translator;
use Crm\ScenariosModule\Repositories\ElementsRepository;

class ScenarioValidator
{
    public function __construct(
        private readonly TriggerOutputParamsRetriever $triggerOutputParamsRetriever,
        private readonly ScenarioConditionValidator $scenarioConditionValidator,
        private readonly Translator $translator,
    ) {
    }

    /**
     * @param array $scenario Scenario structure
     */
    public function validate(array $scenario): void
    {
        $triggers = $scenario['triggers'];

        foreach ($triggers as $trigger) {
            try {
                $triggerOutputParams = $this->triggerOutputParamsRetriever->retrieve($trigger);
            } catch (TriggerOutputParamsRetrieveException) {
                /**
                 * Just skip validation for now if no output params were found as a backward compatibility
                 * measure because handlers was registered via public events instead of TriggerManager in the past.
                 */
                continue;
            }

            $triggerElementUuids = $trigger['elements'];
            $this->validateElements($triggerElementUuids, $triggerOutputParams, $scenario['elements'], $trigger);
        }
    }

    /**
     * @param array $allElements Scenario elements structure
     */
    public function validateElements(array $elementUuids, array $triggerOutputParams, array $allElements, array $trigger): void
    {
        foreach ($elementUuids as $elementUuid) {
            $element = $allElements[$elementUuid];
            $elementType = $element['type'];

            try {
                $this->validateElement($element, $triggerOutputParams);
            } catch (ScenarioElementValidationException $exception) {
                $message = $this->translator->translate('scenarios.admin.scenarios.validation_errors.element_validation_error', [
                    'elementName' => $element['name'],
                    'elementType' => $elementType,
                    'triggerName' => $trigger['name'],
                    'triggerKey' => $trigger['event']['code'],
                    'errorMessage' => $exception->getMessage(),
                ]);

                throw new ScenarioValidationException($message, $trigger['id'], $elementUuid);
            }

            $elementDescendants = $element[$elementType]['descendants'];
            if (!empty($elementDescendants)) {
                $elementDescendantUuids = array_map(
                    fn(array $elementDescendant) => $elementDescendant['uuid'],
                    $elementDescendants
                );

                $this->validateElements($elementDescendantUuids, $triggerOutputParams, $allElements, $trigger);
            }
        }
    }

    private function validateElement(array $element, array $triggerOutputParams): void
    {
        $elementType = $element['type'];

        if ($elementType === ElementsRepository::ELEMENT_TYPE_CONDITION) {
            $this->scenarioConditionValidator->validate($element[$elementType], $triggerOutputParams);
        }
    }
}
