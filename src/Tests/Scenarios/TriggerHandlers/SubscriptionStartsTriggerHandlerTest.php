<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\ScenariosModule\Scenarios\TriggerHandlers\SubscriptionStartsTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class SubscriptionStartsTriggerHandlerTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    public function testKey(): void
    {
        /** @var SubscriptionStartsTriggerHandler $subscriptionStartsTriggerHandler */
        $subscriptionStartsTriggerHandler = $this->inject(SubscriptionStartsTriggerHandler::class);
        $this->assertSame('subscription_starts', $subscriptionStartsTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var SubscriptionStartsTriggerHandler $subscriptionStartsTriggerHandler */
        $subscriptionStartsTriggerHandler = $this->inject(SubscriptionStartsTriggerHandler::class);
        $this->assertSame('subscription-starts', $subscriptionStartsTriggerHandler->getEventType());
    }

    public function testHandleEvent(): void
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

        /** @var SubscriptionStartsTriggerHandler $subscriptionStartsTriggerHandler */
        $subscriptionStartsTriggerHandler = $this->inject(SubscriptionStartsTriggerHandler::class);
        $triggerData = $subscriptionStartsTriggerHandler->handleEvent([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $subscriptionStartsTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingSubscription(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Subscription with ID=1 does not exist");

        /** @var SubscriptionStartsTriggerHandler $subscriptionStartsTriggerHandler */
        $subscriptionStartsTriggerHandler = $this->inject(SubscriptionStartsTriggerHandler::class);
        $subscriptionStartsTriggerHandler->handleEvent([
            'subscription_id' => 1,
        ]);
    }

    public function testHandleEventMissingSubscriptionId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'subscription_id' is missing");

        /** @var SubscriptionStartsTriggerHandler $subscriptionStartsTriggerHandler */
        $subscriptionStartsTriggerHandler = $this->inject(SubscriptionStartsTriggerHandler::class);
        $subscriptionStartsTriggerHandler->handleEvent([]);
    }
}
