<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\ScenariosModule\Scenarios\TriggerHandlers\NewPaymentTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class NewPaymentTriggerHandlerTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    public function testKey(): void
    {
        /** @var NewPaymentTriggerHandler $newPaymentTriggerHandler */
        $newPaymentTriggerHandler = $this->inject(NewPaymentTriggerHandler::class);
        $this->assertSame('new_payment', $newPaymentTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var NewPaymentTriggerHandler $newPaymentTriggerHandler */
        $newPaymentTriggerHandler = $this->inject(NewPaymentTriggerHandler::class);
        $this->assertSame('new-payment', $newPaymentTriggerHandler->getEventType());
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
        $paymentsRepository->addSubscriptionToPayment($subscription, $payment);

        /** @var NewPaymentTriggerHandler $newPaymentTriggerHandler */
        $newPaymentTriggerHandler = $this->inject(NewPaymentTriggerHandler::class);
        $triggerData = $newPaymentTriggerHandler->handleEvent([
            'payment_id' => $payment->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $newPaymentTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
        ], $triggerData->payload);
    }

    public function testHandleEventWithoutSubscription(): void
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

        /** @var NewPaymentTriggerHandler $newPaymentTriggerHandler */
        $newPaymentTriggerHandler = $this->inject(NewPaymentTriggerHandler::class);
        $triggerData = $newPaymentTriggerHandler->handleEvent([
            'payment_id' => $payment->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $newPaymentTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'subscription_id' => null,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingPaymentId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'payment_id' is missing");

        /** @var NewPaymentTriggerHandler $newPaymentTriggerHandler */
        $newPaymentTriggerHandler = $this->inject(NewPaymentTriggerHandler::class);
        $newPaymentTriggerHandler->handleEvent([]);
    }
}
