<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SegmentModule\Seeders\SegmentsSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;
use Ramsey\Uuid\Uuid;

class SegmentCheckEventHandlerTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var SegmentsRepository */
    private $segmentsRepository;

    /** @var JobsRepository */
    private $scenarioJobsRepository;

    /** @var SegmentCheckEventHandler */
    private $segmentCheckEventHandler;

    /** @var ActiveRow */
    private $userRow;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            SubscriptionsRepository::class,
            SubscriptionsRepository::class,
            PaymentsRepository::class,
            SegmentsRepository::class,
            ScenariosRepository::class,
            ElementsRepository::class,
            JobsRepository::class,
            SegmentGroupsRepository::class,
            PaymentsRepository::class,
            PaymentGatewaysRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SegmentsSeeder::class,
            ContentAccessSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->segmentsRepository = $this->getRepository(SegmentsRepository::class);
        $this->scenarioJobsRepository = $this->getRepository(JobsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $this->userRow = $userManager->addNewUser('test@test.sk');


        $this->segmentCheckEventHandler =  $this->inject(SegmentCheckEventHandler::class);
    }

    public function testUserInSegmentPositiveResult(): void
    {
        $segmentRow = $this->prepareSegment(
            'users',
            "SELECT %table%.id FROM %table% WHERE %table%.source = '{$this->userRow->source}' GROUP BY %table%.id",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id]);

        $message = new HermesMessage('user-registered', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);
        $this->assertJsonStringEqualsJsonString(Json::encode(['in' => true]), $scenarioJobRow->result);
    }

    public function testUserInSegmentNegativeResult(): void
    {
        $segmentRow = $this->prepareSegment(
            'users',
            "SELECT %table%.id FROM %table% WHERE %table%.source != '{$this->userRow->source}' GROUP BY %table%.id",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id]);

        $message = new HermesMessage('user-registered', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);
        $this->assertJsonStringEqualsJsonString(Json::encode(['in' => false]), $scenarioJobRow->result);
    }

    public function testSubscriptionInSegmentPositiveResult(): void
    {
        $subscriptionRow = $this->prepareSubscription();
        $segmentRow = $this->prepareSegment(
            'subscriptions',
            "SELECT %table%.id FROM %table% WHERE %table%.subscription_type_id = {$subscriptionRow->subscription_type_id} GROUP BY %table%.id",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id, 'subscription_id' => $subscriptionRow->id]);

        $message = new HermesMessage('subscriptions-created', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);
        $this->assertJsonStringEqualsJsonString(Json::encode(['in' => true]), $scenarioJobRow->result);
    }

    public function testSubscriptionInSegmentNegativeResult(): void
    {
        $subscriptionRow = $this->prepareSubscription();
        $segmentRow = $this->prepareSegment(
            'subscriptions',
            "SELECT %table%.id FROM %table% WHERE %table%.subscription_type_id != {$subscriptionRow->subscription_type_id} GROUP BY %table%.id",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id, 'subscription_id' => $subscriptionRow->id]);

        $message = new HermesMessage('subscriptions-created', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);
        $this->assertJsonStringEqualsJsonString(Json::encode(['in' => false]), $scenarioJobRow->result);
    }

    public function testPaymentInSegmentPositiveResult(): void
    {
        $paymentRow = $this->preparePayment();
        $segmentRow = $this->prepareSegment(
            'payments',
            "SELECT %table%.id FROM %table% WHERE %table%.status IN ('{$paymentRow->status}') GROUP BY %table%.id",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id, 'payment_id' => $paymentRow->id]);

        $message = new HermesMessage('new-payment', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);
        $this->assertJsonStringEqualsJsonString(Json::encode(['in' => true]), $scenarioJobRow->result);
    }

    public function testPaymentInSegmentNegativeResult(): void
    {
        $paymentRow = $this->preparePayment();
        $segmentRow = $this->prepareSegment(
            'payments',
            "SELECT %table%.id FROM %table% WHERE %table%.status NOT IN ('{$paymentRow->status}') GROUP BY %table%.id",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id, 'payment_id' => $paymentRow->id]);

        $message = new HermesMessage('new-payment', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);
        $this->assertJsonStringEqualsJsonString(Json::encode(['in' => false]), $scenarioJobRow->result);
    }

    public function testUnsupportedSegmentSourceTable(): void
    {
        $segmentRow = $this->prepareSegment(
            'subscription_types',
            "SELECT %table%.id FROM %table% WHERE %table%.source = '{$this->userRow->source}' GROUP BY %table%",
        );
        $scenarioJobRow = $this->prepareScenarioJob($segmentRow, ['user_id' => $this->userRow->id]);

        $message = new HermesMessage('user-registered', ['job_id' => $scenarioJobRow->id]);

        $this->segmentCheckEventHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);

        $this->assertEquals(JobsRepository::STATE_FAILED, $scenarioJobRow->state);
    }

    private function prepareScenarioJob(ActiveRow $segmentRow, array $scenarioJobParameters): ActiveRow
    {
        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRow = $scenariosRepository->insert([
            'name' => 'Testing scenarios',
            'visual' => '{}',
            'created_at' => new \DateTime(),
            'modified_at' => new \DateTime(),
            'enabled' => 1,
        ]);

        /** @var ElementsRepository $scenarioElementRepository */
        $scenarioElementRepository = $this->getRepository(ElementsRepository::class);
        $scenarioElementRow = $scenarioElementRepository->insert([
            'scenario_id' => $scenarioRow->id,
            'uuid' => Uuid::uuid4(),
            'name' => 'test element name',
            'type' => ElementsRepository::ELEMENT_TYPE_SEGMENT,
            'options' => Json::encode(['code' => $segmentRow->code]),
        ]);

        /** @var JobsRepository $scenarioJobsRepository */
        $scenarioJobsRepository = $this->getRepository(JobsRepository::class);
        $scenarioJobRow = $scenarioJobsRepository->addElement($scenarioElementRow->id, $scenarioJobParameters);
        $scenarioJobRow = $scenarioJobsRepository->scheduleJob($scenarioJobRow);

        return $scenarioJobRow;
    }

    private function prepareSegment(string $table, string $queryString): ActiveRow
    {
        /** @var SegmentGroupsRepository $segmentGroupRepository */
        $segmentGroupRepository = $this->getRepository(SegmentGroupsRepository::class);
        $segmentGroupRow = $segmentGroupRepository->findBy('name', 'Default group');

        $segmentRow = $this->segmentsRepository->add(
            'Test segment name',
            1,
            'test',
            $table,
            "{$table}.id",
            $queryString,
            $segmentGroupRow,
        );

        return $segmentRow;
    }

    private function prepareSubscription(): ActiveRow
    {
        $subscriptionTypeRow = $this->subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->save();

        return $this->subscriptionsRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $this->userRow,
        );
    }

    private function preparePayment(): ActiveRow
    {
        $subscriptionTypeRow = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', Paypal::GATEWAY_CODE);

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        return $paymentsRepository->add(
            $subscriptionTypeRow,
            $paymentGatewayRow,
            $this->userRow,
            new PaymentItemContainer(),
            null,
            1,
        );
    }
}
