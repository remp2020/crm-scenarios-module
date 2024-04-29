<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\SkipTriggerException;
use Crm\ScenariosModule\Scenarios\TriggerHandlers\SubscriptionEndsTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class SubscriptionEndsTriggerHandlerTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    public function testKey(): void
    {
        /** @var SubscriptionEndsTriggerHandler $subscriptionEndsTriggerHandler */
        $subscriptionEndsTriggerHandler = $this->inject(SubscriptionEndsTriggerHandler::class);
        $this->assertSame('subscription_ends', $subscriptionEndsTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var SubscriptionEndsTriggerHandler $subscriptionEndsTriggerHandler */
        $subscriptionEndsTriggerHandler = $this->inject(SubscriptionEndsTriggerHandler::class);
        $this->assertSame('subscription-ends', $subscriptionEndsTriggerHandler->getEventType());
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

        $subscriptionsEndsSuppressionManager = $this->createMock(SubscriptionEndsSuppressionManager::class);
        $subscriptionsEndsSuppressionManager->method('hasSuppressedNotifications')->willReturn(false);

        /** @var SubscriptionEndsTriggerHandler $subscriptionEndsTriggerHandler */
        $subscriptionEndsTriggerHandler = $this->inject(SubscriptionEndsTriggerHandler::class);
        $triggerData = $subscriptionEndsTriggerHandler->handleEvent([
            'subscription_id' => $subscription->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $subscriptionEndsTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ], $triggerData->payload);
    }

    public function testHandleEventSuppressedNotifications(): void
    {
        $this->expectException(SkipTriggerException::class);

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

        $subscriptionsEndsSuppressionManager = $this->createMock(SubscriptionEndsSuppressionManager::class);
        $subscriptionsEndsSuppressionManager->method('hasSuppressedNotifications')->willReturn(true);

        $subscriptionEndsTriggerHandler = new SubscriptionEndsTriggerHandler($subscriptionsRepository, $subscriptionsEndsSuppressionManager);
        $subscriptionEndsTriggerHandler->handleEvent([
            'subscription_id' => $subscription->id,
        ]);
    }

    public function testHandleEventMissingSubscriptionId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'subscription_id' is missing");

        /** @var SubscriptionEndsTriggerHandler $subscriptionEndsTriggerHandler */
        $subscriptionEndsTriggerHandler = $this->inject(SubscriptionEndsTriggerHandler::class);
        $subscriptionEndsTriggerHandler->handleEvent([]);
    }

    public function testHandleEventMissingSubscription(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Subscription with ID=1 does not exist");

        /** @var SubscriptionEndsTriggerHandler $subscriptionEndsTriggerHandler */
        $subscriptionEndsTriggerHandler = $this->inject(SubscriptionEndsTriggerHandler::class);
        $subscriptionEndsTriggerHandler->handleEvent([
            'subscription_id' => 1,
        ]);
    }
}
