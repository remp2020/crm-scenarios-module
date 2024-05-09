<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\ScenariosModule\Engine\Validator\ScenarioConditionValidator;
use Crm\ScenariosModule\Engine\Validator\ScenarioElementValidationException;
use Crm\ScenariosModule\Engine\Validator\ScenarioValidationException;
use Crm\ScenariosModule\Engine\Validator\ScenarioValidator;
use Crm\ScenariosModule\Engine\Validator\TriggerOutputParamsRetrieveException;
use Crm\ScenariosModule\Engine\Validator\TriggerOutputParamsRetriever;
use Crm\ScenariosModule\Repositories\ElementsRepository;

class ScenarioValidatorTest extends CrmTestCase
{
    /**
     * @dataProvider notValidatableElementsProvider
     */
    public function testValidateNotValidatableElements(string $elementType): void
    {
        $triggerOutputParamsRetriever = $this->createMock(TriggerOutputParamsRetriever::class);
        $triggerOutputParamsRetriever->expects($this->once())
            ->method('retrieve')
            ->willReturn(['first_input_param']);

        $scenarioConditionValidator = $this->createMock(ScenarioConditionValidator::class);
        $scenarioConditionValidator->expects($this->never())
            ->method('validate');

        $translator = $this->createMock(Translator::class);

        /**
         * GRAPH: TRIGGER >--- Element1
         */
        $scenarioValidator = new ScenarioValidator($triggerOutputParamsRetriever, $scenarioConditionValidator, $translator);
        $scenarioValidator->validate([
            'triggers' => [
                [
                    'id' => 'a7d1b65c-8e3e-4b8b-958e-eae9c4c12f2a',
                    'name' => 'Event1',
                    'type' => 'event',
                    'event' => [
                        'code' => 'subscription',
                    ],
                    'elements' => [
                        '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                    ],
                ],
            ],
            'elements' => [
                '0dfdedbb-6d14-49ef-8436-c33cf214104c' => [
                    'type' => $elementType,
                    $elementType => [
                        'id' => '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                        'name' => 'Cond1',
                        'descendants' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testValidateConditions(): void
    {
        $triggerOutputParamsRetriever = $this->createMock(TriggerOutputParamsRetriever::class);
        $triggerOutputParamsRetriever->expects($this->once())
            ->method('retrieve')
            ->willReturn(['first_input_param']);

        $scenarioConditionValidator = $this->createMock(ScenarioConditionValidator::class);
        $scenarioConditionValidator->expects($this->exactly(4))
            ->method('validate');

        $translator = $this->createMock(Translator::class);

        /**
         * GRAPH:
         * ---
         * TRIGGER >--- Cond1 ---> Cond3 ---> Email
         *          \-- Cond2 --|
         */
        $scenarioValidator = new ScenarioValidator($triggerOutputParamsRetriever, $scenarioConditionValidator, $translator);
        $scenarioValidator->validate([
            'triggers' => [
                [
                    'id' => 'a7d1b65c-8e3e-4b8b-958e-eae9c4c12f2a',
                    'name' => 'Event1',
                    'type' => 'event',
                    'event' => [
                        'code' => 'subscription',
                    ],
                    'elements' => [
                        '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                        'd3511ff1-0fdc-4133-bf5b-8edc60f54148',
                    ],
                ],
            ],
            'elements' => [
                '0dfdedbb-6d14-49ef-8436-c33cf214104c' => [
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'id' => '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                        'name' => 'Cond1',
                        'conditions' => [
                            'event' => 'subscription',
                        ],
                        'descendants' => [
                            [
                                'uuid' => '033b6927-5f94-486a-9a34-073135995748'
                            ],
                        ],
                    ],
                ],
                'd3511ff1-0fdc-4133-bf5b-8edc60f54148' => [
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'id' => 'd3511ff1-0fdc-4133-bf5b-8edc60f54148',
                        'name' => 'Cond2',
                        'conditions' => [
                            'event' => 'subscription',
                        ],
                        'descendants' => [
                            [
                                'uuid' => '033b6927-5f94-486a-9a34-073135995748'
                            ],
                        ],
                    ],
                ],
                '033b6927-5f94-486a-9a34-073135995748' => [
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'id' => '033b6927-5f94-486a-9a34-073135995748',
                        'name' => 'Cond3',
                        'conditions' => [
                            'event' => 'subscription',
                        ],
                        'descendants' => [
                            [
                                'uuid' => '593399e4-d234-4d0d-8a42-8a8deaeda9af'
                            ],
                        ],
                    ],
                ],
                '593399e4-d234-4d0d-8a42-8a8deaeda9af' => [
                    'type' => 'email',
                    'email' => [
                        'id' => '593399e4-d234-4d0d-8a42-8a8deaeda9af',
                        'name' => 'Email',
                        'descendants' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testValidateConditionsWithThrow(): void
    {
        $this->expectException(ScenarioValidationException::class);
        $this->expectExceptionMessage("Translated error message");

        $triggerOutputParamsRetriever = $this->createMock(TriggerOutputParamsRetriever::class);
        $triggerOutputParamsRetriever->expects($this->once())
            ->method('retrieve')
            ->willReturn(['first_input_param']);

        $scenarioConditionValidator = $this->createMock(ScenarioConditionValidator::class);
        $scenarioConditionValidator->expects($this->exactly(1))
            ->method('validate')
            ->willThrowException(new ScenarioElementValidationException('Element error'));

        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('scenarios.admin.scenarios.validation_errors.element_validation_error', [
                'elementName' => 'Cond1',
                'elementType' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                'triggerName' => 'Event1',
                'triggerKey' => 'subscription',
                'errorMessage' => 'Element error',
            ])
            ->willReturn('Translated error message');

        /**
         * GRAPH: TRIGGER >--- Cond1
         */
        $scenarioValidator = new ScenarioValidator($triggerOutputParamsRetriever, $scenarioConditionValidator, $translator);
        $scenarioValidator->validate([
            'triggers' => [
                [
                    'id' => 'a7d1b65c-8e3e-4b8b-958e-eae9c4c12f2a',
                    'name' => 'Event1',
                    'type' => 'event',
                    'event' => [
                        'code' => 'subscription',
                    ],
                    'elements' => [
                        '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                    ],
                ],
            ],
            'elements' => [
                '0dfdedbb-6d14-49ef-8436-c33cf214104c' => [
                    'id' => '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                    'name' => 'Cond1',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'conditions' => [
                            'event' => 'subscription',
                        ],
                        'descendants' => [
                            [
                                'uuid' => '033b6927-5f94-486a-9a34-073135995748'
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testValidateNotRegisteredTriggerForBackwardCompatibility(): void
    {
        $triggerOutputParamsRetriever = $this->createMock(TriggerOutputParamsRetriever::class);
        $triggerOutputParamsRetriever->expects($this->once())
            ->method('retrieve')
            ->willThrowException(new TriggerOutputParamsRetrieveException());

        $scenarioConditionValidator = $this->createMock(ScenarioConditionValidator::class);

        $translator = $this->createMock(Translator::class);

        $scenarioValidator = new ScenarioValidator($triggerOutputParamsRetriever, $scenarioConditionValidator, $translator);
        $scenarioValidator->validate([
            'triggers' => [
                [
                    'id' => 'a7d1b65c-8e3e-4b8b-958e-eae9c4c12f2a',
                    'name' => 'Event1',
                    'type' => 'event',
                    'event' => [
                        'code' => 'subscription',
                    ],
                    'elements' => [
                        '0dfdedbb-6d14-49ef-8436-c33cf214104c',
                    ],
                ],
            ],
        ]);
    }

    public static function notValidatableElementsProvider(): array
    {
        return [
            [ElementsRepository::ELEMENT_TYPE_EMAIL],
            [ElementsRepository::ELEMENT_TYPE_GOAL],
            [ElementsRepository::ELEMENT_TYPE_SEGMENT],
            [ElementsRepository::ELEMENT_TYPE_WAIT],
            [ElementsRepository::ELEMENT_TYPE_BANNER],
            [ElementsRepository::ELEMENT_TYPE_GENERIC],
            [ElementsRepository::ELEMENT_TYPE_PUSH_NOTIFICATION],
            [ElementsRepository::ELEMENT_TYPE_ABTEST],
        ];
    }
}
