<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\SkipTriggerException;
use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Exception;

class NewSubscriptionTriggerHandler implements TriggerHandlerInterface
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'New subscription';
    }

    public function getKey(): string
    {
        return 'new_subscription';
    }

    public function getEventType(): string
    {
        return 'new-subscription';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'subscription_id', 'payment_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['subscription_id'])) {
            throw new Exception("'subscription_id' is missing");
        }

        $sendEmail = $data['send_email'] ?? true;
        if (!$sendEmail) {
            throw new SkipTriggerException();
        }

        $subscriptionId = $data['subscription_id'];
        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            throw new Exception(sprintf(
                "Subscription with ID=%s does not exist",
                $subscriptionId,
            ));
        }

        $payload = [
            'user_id' => $subscription->user_id,
            'subscription_id' => $data['subscription_id'],
            'payment_id' => null,
        ];

        $payment = $subscription->related('payments')->limit(1)->fetch();
        if ($payment) {
            $payload['payment_id'] = $payment->id;
        }

        return new TriggerData($subscription->user_id, $payload);
    }
}
