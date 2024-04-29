<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\SkipTriggerException;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\ScenariosModule\Scenarios\TriggerHandlers\NewSubscriptionTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class NewSubscriptionTriggerHandlerTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    public function testKey(): void
    {
        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $this->assertSame('new_subscription', $newSubscriptionTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $this->assertSame('new-subscription', $newSubscriptionTriggerHandler->getEventType());
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

        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $triggerData = $newSubscriptionTriggerHandler->handleEvent([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $newSubscriptionTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
        ], $triggerData->payload);
    }

    public function testHandleEventWithoutPayment(): void
    {
        $subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

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

        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $triggerData = $newSubscriptionTriggerHandler->handleEvent([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $newSubscriptionTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'payment_id' => null,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingSubscriptionId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'subscription_id' is missing");

        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $newSubscriptionTriggerHandler->handleEvent([]);
    }

    public function testHandleEventSkipTrigger(): void
    {
        $this->expectException(SkipTriggerException::class);

        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $newSubscriptionTriggerHandler->handleEvent([
            'subscription_id' => 1,
            'send_email' => false
        ]);
    }

    public function testHandleEventMissingSubscription(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Subscription with ID=1 does not exist");

        /** @var NewSubscriptionTriggerHandler $newSubscriptionTriggerHandler */
        $newSubscriptionTriggerHandler = $this->inject(NewSubscriptionTriggerHandler::class);
        $newSubscriptionTriggerHandler->handleEvent([
            'subscription_id' => 1,
        ]);
    }
}
