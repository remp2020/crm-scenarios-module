<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\InvoicesModule\Models\InvoiceNumber\InvoiceNumber;
use Crm\InvoicesModule\Repositories\InvoiceNumbersRepository;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\ScenariosModule\Scenarios\TriggerHandlers\NewInvoiceTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class NewInvoiceTriggerHandlerTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;
    private InvoiceNumber $invoiceNumber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->invoiceNumber = $this->inject(InvoiceNumber::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            InvoiceNumbersRepository::class,
            AddressesRepository::class,
            InvoicesRepository::class,
        ];
    }

    public function testKey(): void
    {
        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $this->assertSame('new_invoice', $newInvoiceTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $this->assertSame('new-invoice', $newInvoiceTriggerHandler->getEventType());
    }

    public function testHandleEvent(): void
    {
        $subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $gateway = $paymentGatewaysRepository->add('Gateway 1', 'gateway1');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);
        $addressesRepository->add(
            $user,
            'invoice',
            firstName: 'John',
            lastName: 'Doe',
            street: 'Main Street',
            number: '123',
            city: 'New York',
            zip: '12345',
            countryId: null,
            phoneNumber: '+1234567890',
        );

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $subscription = $subscriptionsRepository->add(
            $subscriptionType,
            isRecurrent: false,
            isPaid: true,
            user: $user,
        );

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $payment = $paymentsRepository->add(
            $subscriptionType,
            $gateway,
            $user,
            new PaymentItemContainer(),
            amount: 1,
        );
        $paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        $paymentsRepository->addSubscriptionToPayment($subscription, $payment);
        $payment = $paymentsRepository->find($payment->id);

        $invoiceNumber = $this->invoiceNumber->getNextInvoiceNumber($payment);
        $paymentsRepository->update($payment, ['invoice_number_id' => $invoiceNumber]);

        /** @var InvoicesRepository $invoicesRepository */
        $invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $invoice = $invoicesRepository->add($user, $payment);

        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $triggerData = $newInvoiceTriggerHandler->handleEvent([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $newInvoiceTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingInvoice(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invoice with ID=1 does not exist");

        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $newInvoiceTriggerHandler->handleEvent([
            'invoice_id' => 1,
        ]);
    }

    public function testHandleEventMissingPayment(): void
    {
        $this->expectException(Exception::class);

        $subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $gateway = $paymentGatewaysRepository->add('Gateway 1', 'gateway1');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);
        $addressesRepository->add(
            $user,
            'invoice',
            firstName: 'John',
            lastName: 'Doe',
            street: 'Main Street',
            number: '123',
            city: 'New York',
            zip: '12345',
            countryId: null,
            phoneNumber: '+1234567890',
        );

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $subscription = $subscriptionsRepository->add(
            $subscriptionType,
            isRecurrent: false,
            isPaid: true,
            user: $user,
        );

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $payment = $paymentsRepository->add(
            $subscriptionType,
            $gateway,
            $user,
            new PaymentItemContainer(),
            amount: 1,
        );
        $paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        $paymentsRepository->addSubscriptionToPayment($subscription, $payment);
        $payment = $paymentsRepository->find($payment->id);

        $invoiceNumber = $this->invoiceNumber->getNextInvoiceNumber($payment);
        $paymentsRepository->update($payment, ['invoice_number_id' => $invoiceNumber]);

        /** @var InvoicesRepository $invoicesRepository */
        $invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $invoice = $invoicesRepository->add($user, $payment);

        $paymentsRepository->delete($payment);

        $this->expectExceptionMessage(sprintf('No payment related to invoice ID=%d', $invoice->id));

        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $newInvoiceTriggerHandler->handleEvent([
            'invoice_id' => $invoice->id,
        ]);
    }

    public function testHandleEventMissingSubscription(): void
    {
        $subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $gateway = $paymentGatewaysRepository->add('Gateway 1', 'gateway1');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);
        $addressesRepository->add(
            $user,
            'invoice',
            firstName: 'John',
            lastName: 'Doe',
            street: 'Main Street',
            number: '123',
            city: 'New York',
            zip: '12345',
            countryId: null,
            phoneNumber: '+1234567890',
        );

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $payment = $paymentsRepository->add(
            $subscriptionType,
            $gateway,
            $user,
            new PaymentItemContainer(),
            amount: 1,
        );
        $paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        $payment = $paymentsRepository->find($payment->id);

        $invoiceNumber = $this->invoiceNumber->getNextInvoiceNumber($payment);
        $paymentsRepository->update($payment, ['invoice_number_id' => $invoiceNumber]);

        /** @var InvoicesRepository $invoicesRepository */
        $invoicesRepository = $this->getRepository(InvoicesRepository::class);
        $invoice = $invoicesRepository->add($user, $payment);

        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $triggerData = $newInvoiceTriggerHandler->handleEvent([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingInvoiceId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'invoice_id' is missing");

        /** @var NewInvoiceTriggerHandler $newInvoiceTriggerHandler */
        $newInvoiceTriggerHandler = $this->inject(NewInvoiceTriggerHandler::class);
        $newInvoiceTriggerHandler->handleEvent([]);
    }
}
