<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Event\BeforeEvent;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Events\BeforeEventGenerator;
use Crm\ScenariosModule\Events\EventGenerators\BeforeRecurrentPaymentChargeEventGenerator;
use Crm\ScenariosModule\Repositories\GeneratedEventsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Repositories\UsersRepository;
use DateTime;

class BeforeRecurrentPaymentChargeEventGeneratorTest extends BaseTestCase
{
    protected BeforeEventGenerator $beforeEventGenerator;
    private RecurrentPaymentsRepository $recurrentPaymentsRepository;
    private JobsRepository $scenariosJobsRepository;

    public function setUp(): void
    {
        parent::setUp();

        /** @var TriggersRepository $triggerRepository */
        $triggerRepository = $this->getRepository(TriggersRepository::class);

        /** @var GeneratedEventsRepository $generatedEventsRepository */
        $generatedEventsRepository = $this->getRepository(GeneratedEventsRepository::class);

        /** @var Dispatcher $dispatcher */
        $dispatcher = $this->inject(Dispatcher::class);

        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);

        $beforeRecurrentPaymentChargeEventGenerator = new BeforeRecurrentPaymentChargeEventGenerator($this->recurrentPaymentsRepository);
        $this->eventsStorage->registerEventGenerator('before_recurrent_payment_charge', $beforeRecurrentPaymentChargeEventGenerator);

        $this->beforeEventGenerator = new BeforeEventGenerator(
            $this->eventsStorage,
            $triggerRepository,
            $generatedEventsRepository,
            $dispatcher
        );

        $this->scenariosJobsRepository = $this->getRepository(JobsRepository::class);
    }

    protected function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            GeneratedEventsRepository::class,
        ]);
    }

    public function testValid(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'before_recurrent_payment_charge'],
                    'options' => self::obj(["minutes" => $minutes]),
                ])
            ]
        ]);

        $user = $this->loadUser('test@test.com');
        $recurrentPayment = $this->createRecurrentPayment($user, $minutes, true);

        $result = $this->beforeEventGenerator->generate();

        $this->assertCount(1, $result);

        /** @var BeforeEvent $beforeEvent */
        $beforeEvent = current($result)[0];
        $this->assertEquals($recurrentPayment->id, $beforeEvent->getId());
        $this->assertEquals($user->id, $beforeEvent->getUserId());
        $this->assertEquals($recurrentPayment->id, $beforeEvent->getParameters()['recurrent_payment_id']);

        $this->assertEquals(1, $this->scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(1, $this->scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    public function testNotFirstCharge(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'before_recurrent_payment_charge'],
                    'options' => self::obj(["minutes" => $minutes]),
                ])
            ]
        ]);

        $user = $this->loadUser('test@test.com');
        $recurrentPayment = $this->createRecurrentPayment($user, $minutes, false);

        $result = $this->beforeEventGenerator->generate();

        $this->assertCount(0, $result);

        $this->assertEquals(0, $this->scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(0, $this->scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    public function testChargeBeforeThreshold(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'before_recurrent_payment_charge'],
                    'options' => self::obj(["minutes" => $minutes]),
                ])
            ]
        ]);

        $user = $this->loadUser('test@test.com');
        $recurrentPayment = $this->createRecurrentPayment($user, $minutes + 100, false);

        $result = $this->beforeEventGenerator->generate();

        $this->assertCount(0, $result);

        $this->assertEquals(0, $this->scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(0, $this->scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    private function createRecurrentPayment($user, $minutes, $firstCharge)
    {
        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);

        $paymentGateway = $paymentGatewaysRepository->add('test', 'test', 10, true, true);
        $paymentItemContainer = (new PaymentItemContainer())->addItems([new DonationPaymentItem('donation', 10, 0)]);

        $payment = $paymentsRepository->add($this->getSubscriptionType(), $paymentGateway, $user, $paymentItemContainer);
        if ($firstCharge) {
            $paymentsRepository->update($payment, ['status' => PaymentsRepository::STATUS_PAID]);
        } else {
            $paymentsRepository->update($payment, ['status' => PaymentsRepository::STATUS_FAIL]);
        }

        return $this->recurrentPaymentsRepository->add('111', $payment, new DateTime("+ {$minutes} minutes"), 0, 5);
    }

    private function loadUser($email)
    {
        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);

        $user = $usersRepository->getByEmail($email);
        if (!$user) {
            $user = $usersRepository->add($email, 'password123');
        }
        return $user;
    }

    private function getSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        return $subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();
    }
}
