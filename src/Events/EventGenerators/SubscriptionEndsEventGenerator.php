<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Event\BeforeEvent;
use Crm\ApplicationModule\Event\EventGeneratorInterface;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class SubscriptionEndsEventGenerator implements EventGeneratorInterface
{
    public const BEFORE_EVENT_CODE = 'subscription_ends';

    public function __construct(
        private SubscriptionsRepository $subscriptionsRepository,
        private SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager,
    ) {
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
            $parameters['subscription_id'] = $subscriptionRow->id;
            $payment = $subscriptionRow->related('payments')->limit(1)->fetch();
            if ($payment) {
                $parameters['payment_id'] = $payment->id;
            }

            if ($this->subscriptionEndsSuppressionManager->hasSuppressedNotifications($subscriptionRow)) {
                return null;
            }

            return new BeforeEvent($subscriptionRow->id, $subscriptionRow->user_id, $parameters);
        }, $this->subscriptionsRepository->subscriptionsEndBetween($endTimeFrom, $endTimeTo)->fetchAll()));
    }
}
