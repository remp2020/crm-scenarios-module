<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\ScenariosModule\Engine\Validator\ScenarioConditionValidator;
use Crm\ScenariosModule\Engine\Validator\ScenarioValidationException;
use Crm\ScenariosModule\Engine\Validator\ScenarioValidator;
use Crm\ScenariosModule\Engine\Validator\TriggerOutputParamsRetriever;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\ScenariosModule\Scenarios\HasPaymentCriteria;
use Crm\SubscriptionsModule\Scenarios\SubscriptionScenarioConditionModel;
use Crm\UsersModule\Scenarios\UserScenarioConditionModel;

class RepeatingTriggerValidationTest extends CrmTestCase
{
    private ScenarioValidator $scenarioValidator;

    protected function setUp(): void
    {
        parent::setUp();

        // Criteria are registered by module code, which is not executed in tests. The storage is shared
        // between test cases and rejects a repeated registration, so register each model only once.
        $criteriaStorage = $this->inject(ScenariosCriteriaStorage::class);
        $criteriaStorage->register('trigger', HasPaymentCriteria::KEY, $this->inject(HasPaymentCriteria::class));
        if (!$criteriaStorage->getConditionModel('user')) {
            $criteriaStorage->registerConditionModel('user', $this->inject(UserScenarioConditionModel::class));
        }
        if (!$criteriaStorage->getConditionModel('subscription')) {
            $criteriaStorage->registerConditionModel('subscription', $this->inject(SubscriptionScenarioConditionModel::class));
        }

        $translator = $this->createStub(Translator::class);
        $this->scenarioValidator = new ScenarioValidator(
            $this->inject(TriggerOutputParamsRetriever::class),
            new ScenarioConditionValidator($criteriaStorage, $translator),
            $translator,
        );
    }

    public function testUserConditionIsAllowed(): void
    {
        $this->expectNotToPerformAssertions();

        $this->scenarioValidator->validate($this->scenarioWithCondition('user', 'user_created_at'));
    }

    public function testSubscriptionConditionIsRejected(): void
    {
        $this->expectException(ScenarioValidationException::class);

        $this->scenarioValidator->validate($this->scenarioWithCondition('subscription', 'subscription_type'));
    }

    public function testHasPaymentTriggerCriteriaIsRejected(): void
    {
        $this->expectException(ScenarioValidationException::class);

        $this->scenarioValidator->validate($this->scenarioWithCondition('trigger', HasPaymentCriteria::KEY));
    }

    private function scenarioWithCondition(string $conditionEvent, string $criterionKey): array
    {
        $elementUuid = '0dfdedbb-6d14-49ef-8436-c33cf214104c';

        return [
            'triggers' => [
                [
                    'id' => 'a7d1b65c-8e3e-4b8b-958e-eae9c4c12f2a',
                    'name' => 'Every Monday',
                    'type' => TriggersRepository::TRIGGER_TYPE_REPEATING,
                    'event' => ['code' => RepeatingTrigger::EVENT_CODE],
                    'options' => ['segment_code' => 'tests_all_users', 'days' => [1], 'time' => '09:00'],
                    'elements' => [$elementUuid],
                ],
            ],
            'elements' => [
                $elementUuid => [
                    'id' => $elementUuid,
                    'name' => 'Cond1',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    ElementsRepository::ELEMENT_TYPE_CONDITION => [
                        'descendants' => [],
                        'conditions' => [
                            'event' => $conditionEvent,
                            'nodes' => [
                                [
                                    'key' => $criterionKey,
                                    'params' => [
                                        ['values' => ['selection' => true]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
