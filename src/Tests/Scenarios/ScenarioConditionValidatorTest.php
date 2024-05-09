<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelRequirementsInterface;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ScenariosModule\Engine\Validator\ScenarioConditionValidator;
use Crm\ScenariosModule\Engine\Validator\ScenarioElementValidationException;
use Crm\ScenariosModule\Scenarios\ScenariosTriggerCriteriaRequirementsInterface;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use PHPUnit\Framework\TestCase;

class ScenarioConditionValidatorTest extends TestCase
{
    private ScenariosCriteriaStorage $criteriaStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->criteriaStorage = new ScenariosCriteriaStorage();
    }

    public function testValidateConditionModel(): void
    {
        $this->expectNotToPerformAssertions();

        $this->criteriaStorage->registerConditionModel('subscription', $this->getSampleConditionModel());

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'subscription',
            ],
        ], ['first_input_param', 'second_input_param']);
    }

    public function testValidateWithMissingConditions(): void
    {
        $this->expectNotToPerformAssertions();

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
        ], ['first_input_param', 'second_input_param']);
    }

    public function testValidateConditionModelWithMissingParamsFromTrigger(): void
    {
        $this->expectException(ScenarioElementValidationException::class);
        $this->expectExceptionMessage("Translated error message");

        $this->criteriaStorage->registerConditionModel('subscription', $this->getSampleConditionModel());

        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('scenarios.admin.scenarios.validation_errors.incompatible_criteria_with_trigger')
            ->willReturn('Translated error message');

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'subscription',
            ],
        ], []);
    }

    public function testValidateConditionModelWithAdditionalParamsFromTrigger(): void
    {
        $this->expectNotToPerformAssertions();

        $this->criteriaStorage->registerConditionModel('subscription', $this->getSampleConditionModel());

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'subscription',
            ],
        ], ['first_input_param', 'second_input_param', 'third_additional_input_param']);
    }

    public function testValidateConditionModelWithNotImplementedRequirements(): void
    {
        $this->expectNotToPerformAssertions();

        $this->criteriaStorage->registerConditionModel('subscription', $this->getSampleConditionModelWithoutRequirements());

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'subscription',
            ],
        ], ['first_input_param']);
    }

    public function testValidateTrigger(): void
    {
        $this->expectNotToPerformAssertions();

        $this->criteriaStorage->register('trigger', 'with_requirements', $this->getSampleCriteria());

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'trigger',
                'nodes' => [
                    [
                        'key' => 'with_requirements',
                    ],
                ],
            ],
        ], ['first_input_param', 'second_input_param']);
    }

    public function testValidateTriggerWithMissingParamsFromTrigger(): void
    {
        $this->expectException(ScenarioElementValidationException::class);
        $this->expectExceptionMessage("Translated error message");

        $this->criteriaStorage->register('trigger', 'with_requirements', $this->getSampleCriteria());

        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('scenarios.admin.scenarios.validation_errors.incompatible_criteria_with_trigger')
            ->willReturn('Translated error message');

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'trigger',
                'nodes' => [
                    [
                        'key' => 'with_requirements',
                    ],
                ],
            ],
        ], []);
    }

    public function testValidateTriggerWithAdditionalParamsFromTrigger(): void
    {
        $this->expectNotToPerformAssertions();

        $this->criteriaStorage->register('trigger', 'with_requirements', $this->getSampleCriteria());

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'trigger',
                'nodes' => [
                    [
                        'key' => 'with_requirements',
                    ],
                ],
            ],
        ], ['first_input_param', 'second_input_param', 'third_additional_input_param']);
    }

    public function testValidateTriggerWithNotImplementedRequirements(): void
    {
        $this->expectNotToPerformAssertions();

        $this->criteriaStorage->register('trigger', 'without_requirements', $this->getSampleCriteriaWithoutRequirements());

        $translator = $this->createMock(Translator::class);

        $scenarioConditionValidator = new ScenarioConditionValidator($this->criteriaStorage, $translator);
        $scenarioConditionValidator->validate([
            'id' => 'e5da1f60-73eb-4bea-8fea-a4b64d6cbe3b',
            'name' => 'Example criteria 1',
            'conditions' => [
                'event' => 'trigger',
                'nodes' => [
                    [
                        'key' => 'without_requirements',
                    ],
                ],
            ],
        ], ['first_input_param']);
    }

    private function getSampleCriteria(): ScenariosCriteriaInterface
    {
        return new class implements ScenariosCriteriaInterface, ScenariosTriggerCriteriaRequirementsInterface {
            public function getInputParams(): array
            {
                return ['first_input_param', 'second_input_param'];
            }

            public function params(): array
            {
                throw new Exception('Not implemented');
            }

            public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
            {
                throw new Exception('Not implemented');
            }

            public function label(): string
            {
                throw new Exception('Not implemented');
            }
        };
    }

    private function getSampleConditionModel(): ScenarioConditionModelInterface
    {
        return new class implements ScenarioConditionModelInterface, ScenarioConditionModelRequirementsInterface {
            public function getInputParams(): array
            {
                return ['first_input_param', 'second_input_param'];
            }

            public function getItemQuery($scenarioJobParameters): \Crm\ApplicationModule\Models\Database\Selection
            {
                throw new Exception('Not implemented');
            }
        };
    }

    private function getSampleConditionModelWithoutRequirements(): ScenarioConditionModelInterface
    {
        return new class implements ScenarioConditionModelInterface {
            public function getItemQuery($scenarioJobParameters): \Crm\ApplicationModule\Models\Database\Selection
            {
                throw new Exception('Not implemented');
            }
        };
    }

    private function getSampleCriteriaWithoutRequirements(): ScenariosCriteriaInterface
    {
        return new class implements ScenariosCriteriaInterface {
            public function params(): array
            {
                throw new Exception('Not implemented');
            }

            public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
            {
                throw new Exception('Not implemented');
            }

            public function label(): string
            {
                throw new Exception('Not implemented');
            }
        };
    }
}
