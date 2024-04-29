<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Scenarios\TriggerHandlers\RecurrentPaymentRenewedTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;
use Nette\Utils\DateTime;

class RecurrentPaymentRenewedTriggerHandlerTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    public function testKey(): void
    {
        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $this->assertSame('recurrent_payment_renewed', $recurrentPaymentRenewedTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $this->assertSame('recurrent-payment-renewed', $recurrentPaymentRenewedTriggerHandler->getEventType());
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

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $subscription = $subscriptionsRepository->add(
            $subscriptionType,
            isRecurrent: false,
            isPaid: true,
            user: $user
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
        $paymentsRepository->addSubscriptionToPayment($subscription, $payment);

        /** @var RecurrentPaymentsRepository $recurrentPaymentsRepository */
        $recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $recurrentPayment = $recurrentPaymentsRepository->add(
            'someCid',
            $payment,
            chargeAt: new DateTime(),
            customAmount: null,
            retries: 0,
        );
        $recurrentPaymentsRepository->setCharged($recurrentPayment, $payment, RecurrentPaymentsRepository::STATE_CHARGED, '');

        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $triggerData = $recurrentPaymentRenewedTriggerHandler->handleEvent([
            'recurrent_payment_id' => $recurrentPayment->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $recurrentPaymentRenewedTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'recurrent_payment_id' => $recurrentPayment->id,
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingSubscriptionId(): void
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

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $payment = $paymentsRepository->add(
            $subscriptionType,
            $gateway,
            $user,
            new PaymentItemContainer(),
            amount: 1,
        );

        /** @var RecurrentPaymentsRepository $recurrentPaymentsRepository */
        $recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $recurrentPayment = $recurrentPaymentsRepository->add(
            'someCid',
            $payment,
            chargeAt: new DateTime(),
            customAmount: null,
            retries: 0,
        );
        $recurrentPaymentsRepository->setCharged($recurrentPayment, $payment, RecurrentPaymentsRepository::STATE_CHARGED, '');

        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $triggerData = $recurrentPaymentRenewedTriggerHandler->handleEvent([
            'recurrent_payment_id' => $recurrentPayment->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $recurrentPaymentRenewedTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'recurrent_payment_id' => $recurrentPayment->id,
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingPayment(): void
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

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $payment = $paymentsRepository->add(
            $subscriptionType,
            $gateway,
            $user,
            new PaymentItemContainer(),
            amount: 1,
        );

        /** @var RecurrentPaymentsRepository $recurrentPaymentsRepository */
        $recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $recurrentPayment = $recurrentPaymentsRepository->add(
            'someCid',
            $payment,
            chargeAt: new DateTime(),
            customAmount: null,
            retries: 0,
        );

        $this->expectExceptionMessage(sprintf(
            'Recurrent payment with ID=%d has no payment assigned',
            $recurrentPayment->id
        ));

        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $recurrentPaymentRenewedTriggerHandler->handleEvent([
            'recurrent_payment_id' => $recurrentPayment->id,
        ]);
    }

    public function testHandleEventMissingRecurrentPaymentId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'recurrent_payment_id' is missing");

        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $recurrentPaymentRenewedTriggerHandler->handleEvent([]);
    }

    public function testHandleEventMissingRecurrentPayment(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Recurrent payment with ID=1 does not exist");

        /** @var RecurrentPaymentRenewedTriggerHandler $recurrentPaymentRenewedTriggerHandler */
        $recurrentPaymentRenewedTriggerHandler = $this->inject(RecurrentPaymentRenewedTriggerHandler::class);
        $recurrentPaymentRenewedTriggerHandler->handleEvent([
            'recurrent_payment_id' => 1,
        ]);
    }
}
