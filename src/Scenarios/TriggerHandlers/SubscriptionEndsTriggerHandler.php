<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\SkipTriggerException;
use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Exception;

class SubscriptionEndsTriggerHandler implements TriggerHandlerInterface
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager,
    ) {
    }

    public function getName(): string
    {
        return 'Subscription ends';
    }

    public function getKey(): string
    {
        return 'subscription_ends';
    }

    public function getEventType(): string
    {
        return 'subscription-ends';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'subscription_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['subscription_id'])) {
            throw new Exception("'subscription_id' is missing");
        }

        $subscriptionId = $data['subscription_id'];
        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            throw new Exception(sprintf(
                "Subscription with ID=%s does not exist",
                $subscriptionId
            ));
        }

        if ($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($subscription)) {
            throw new SkipTriggerException();
        }

        return new TriggerData($subscription->user_id, [
            'user_id' => $subscription->user_id,
            'subscription_id' => $data['subscription_id'],
        ]);
    }
}
