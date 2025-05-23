<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumber;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Models\Gateways\BankTransfer;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggerStatsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Generator\SubscriptionsGenerator;
use Crm\SubscriptionsModule\Models\Generator\SubscriptionsParams;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\AddressChangedEvent;
use Crm\UsersModule\Events\NewAddressEvent;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\AddressChangeRequestsRepository;
use Crm\UsersModule\Repositories\AddressTypesRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Emitter;

class ScenarioTriggersTest extends BaseTestCase
{
    private UserManager $userManager;

    private Emitter $hermesEmitter;

    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    private SubscriptionsGenerator $subscriptionGenerator;

    private SubscriptionsRepository $subscriptionRepository;

    private PaymentsRepository $paymentsRepository;

    private AddressesRepository $addressesRepository;

    private AddressChangeRequestsRepository $addressChangeRequestRepository;

    private InvoicesRepository $invoicesRepository;

    protected function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            AddressChangeRequestsRepository::class,
            AddressesRepository::class,
            AddressTypesRepository::class,
            InvoicesRepository::class,
            InvoiceNumbersRepository::class,
        ]);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->userManager = $this->inject(UserManager::class);
        $this->hermesEmitter = $this->inject(Emitter::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionGenerator = $this->inject(SubscriptionsGenerator::class);
        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->addressesRepository = $this->getRepository(AddressesRepository::class);
        $this->addressChangeRequestRepository = $this->getRepository(AddressChangeRequestsRepository::class);
        $this->invoicesRepository = $this->getRepository(InvoicesRepository::class);

        $this->eventsStorage->register('new_address', NewAddressEvent::class, true);
        $this->eventsStorage->register('address_changed', AddressChangedEvent::class, true);
    }

    public function testTriggerUserRegisteredScenario()
    {
        $this->addTestScenario('user_registered');

        // Add user, which triggers scenario
        $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(2); // process trigger

        $this->userManager->addNewUser('user2@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(2); // process trigger

        $this->userManager->addNewUser('user3@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(2); // process trigger

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Check stats
        // Triggers are only CREATED and then FINISHED
        /** @var TriggerStatsRepository $tsr */
        $tsr = $this->getRepository(TriggerStatsRepository::class);
        $triggerStats = $tsr->countsForTriggers([$this->triggerId('trigger1')], new DateTime('-1 hour'));
        $this->assertEquals(3, $triggerStats[$this->triggerId('trigger1')][JobsRepository::STATE_FINISHED]);
    }

    public function testTriggerNewSubscription()
    {
        $this->addTestScenario('new_subscription');

        $user1 = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(62)
            ->save();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user1,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime('now - 1 month'),
            new DateTime('now + 1 month'),
            false,
        ), 1);

        $subscription = $this->subscriptionRepository->actualUserSubscription($user1->id);

        $this->dispatcher->handle();

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);

        // There should be 1 trigger now
        $this->assertEquals(1, count($jobsRepository->getUnprocessedJobs()));

        $this->hermesEmitter->emit(new HermesMessage('new-subscription', [
            'subscription_id' => $subscription->id,
            'send_email' => true,
        ]));
        $this->dispatcher->handle();

        // There should be 2 triggers now
        $this->assertEquals(2, count($jobsRepository->getUnprocessedJobs()));

        // If send_email is false, we shouldn't trigger new-subscriptions scenario
        $this->hermesEmitter->emit(new HermesMessage('new-subscription', [
            'subscription_id' => $subscription->id,
            'send_email' => false,
        ]));
        $this->dispatcher->handle();

        // There should be 2 triggers now
        $this->assertEquals(2, count($jobsRepository->getUnprocessedJobs()));
    }

    public function testTriggerNewPayment()
    {
        $this->addTestScenario('new_payment');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', BankTransfer::GATEWAY_CODE);

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Trigger the scenario
        $this->paymentsRepository->add(
            null,
            $paymentGatewayRow,
            $user1,
            new PaymentItemContainer(),
            null,
            1,
        );

        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    public function testTriggerPaymentChangeStatus()
    {
        $this->addTestScenario('payment_change_status');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', BankTransfer::GATEWAY_CODE);

        $paymentRow = $this->paymentsRepository->add(
            null,
            $paymentGatewayRow,
            $user1,
            new PaymentItemContainer(),
            null,
            1,
        );
        $this->dispatcher->handle();

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Trigger the scenario
        $this->paymentsRepository->updateStatus($paymentRow, PaymentStatusEnum::Paid->value, true);
        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    public function testTriggerNewAddressHandler()
    {
        $this->addTestScenario('new_address');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        $request = $this->addressChangeRequestRepository->add(
            $user1,
            false,
            'Jan',
            'Novak',
            'Company s.r.o.',
            'Rovná',
            '123',
            'Bratislava',
            '123 12',
            null,
            null,
            null,
            null,
            null,
            'print',
        );

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->addressChangeRequestRepository->acceptRequest($request);
        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    public function testTriggerAddressChangedHandler()
    {
        $this->addTestScenario('address_changed');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        $address = $this->addressesRepository->add(
            $user1,
            'print',
            'Jan',
            'Novak',
            'Rovná',
            '123',
            'Bratislava',
            '123 12',
            null,
            null,
            null,
        );

        $request = $this->addressChangeRequestRepository->add(
            $user1,
            $address,
            'Jan',
            'Novak',
            'Company s.r.o.',
            'Rovná',
            '321',
            'Bratislava',
            '123 12',
            null,
            null,
            null,
            null,
            null,
            'print',
        );

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->addressChangeRequestRepository->acceptRequest($request);
        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    public function testTriggerNewInvoiceHandler()
    {
        $this->addTestScenario('new_invoice');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', BankTransfer::GATEWAY_CODE);

        $this->addressesRepository->add(
            $user1,
            'invoice',
            'Jan',
            'Novak',
            'Rovná',
            '123',
            'Bratislava',
            '123 12',
            null,
            null,
            null,
        );

        $paymentRow = $this->paymentsRepository->add(
            null,
            $paymentGatewayRow,
            $user1,
            new PaymentItemContainer(),
            null,
            1,
        );
        $paymentRow = $this->paymentsRepository->updateStatus($paymentRow, PaymentStatusEnum::Paid->value);

        $invoiceNumber = $this->inject(InvoiceNumber::class);
        $this->paymentsRepository->update($paymentRow, [
            'invoice_number_id' => $invoiceNumber->getNextInvoiceNumber($paymentRow)->id,
        ]);

        $this->dispatcher->handle();
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Trigger the scenario
        $this->invoicesRepository->add($user1, $paymentRow);

        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    private function addTestScenario($triggerCode)
    {
        $scenarioRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => $triggerCode],
                ]),
            ],
        ]);
    }
}
