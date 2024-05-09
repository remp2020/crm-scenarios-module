<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Models\Event\BeforeEvent;
use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Models\Event\EventGeneratorOutputProviderInterface;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SubscriptionEndsEventGenerator implements EventGeneratorInterface, EventGeneratorOutputProviderInterface
{
    public const BEFORE_EVENT_CODE = 'subscription_ends';

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager,
    ) {
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'subscription_id', 'payment_id'];
    }

    /**
     * @param DateInterval $timeOffset
     * @return BeforeEvent[]
     */
    public function generate(DateInterval $timeOffset): array
    {
        $endTimeTo = new DateTime();
        $endTimeTo->add($timeOffset);

        $endTimeFrom = $endTimeTo->modifyClone("-30 minutes");

        return array_filter(array_map(function (ActiveRow $subscriptionRow) {
            $parameters['user_id'] = $subscriptionRow->user_id;
            $parameters['subscription_id'] = $subscriptionRow->id;

            $payment = $subscriptionRow->related('payments')->limit(1)->fetch();
            $parameters['payment_id'] = $payment?->id;

            if ($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($subscriptionRow)) {
                return null;
            }

            return new BeforeEvent($subscriptionRow->id, $subscriptionRow->user_id, $parameters);
        }, $this->subscriptionsRepository->subscriptionsEndBetween($endTimeFrom, $endTimeTo)->fetchAll()));
    }
}
