<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Exception;

class SubscriptionUpgradedTriggerHandler implements TriggerHandlerInterface
{
    public const string EVENT_TYPE = 'subscription-upgraded';

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'Subscription upgraded';
    }

    public function getKey(): string
    {
        return 'subscription_upgraded';
    }

    public function getEventType(): string
    {
        return self::EVENT_TYPE;
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'subscription_id', 'upgrade_type'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['subscription_id'])) {
            throw new Exception("'subscription_id' is missing");
        }
        if (!isset($data['upgrade_type'])) {
            throw new Exception("'upgrade_type' is missing");
        }

        $subscriptionId = $data['subscription_id'];
        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            throw new Exception(sprintf(
                "Subscription with ID=%s does not exist",
                $subscriptionId,
            ));
        }

        return new TriggerData($subscription->user_id, [
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'upgrade_type' => $data['upgrade_type'],
        ]);
    }
}
