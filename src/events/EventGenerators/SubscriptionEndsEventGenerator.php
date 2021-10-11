<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Event\BeforeEvent;
use Crm\ApplicationModule\Event\EventGeneratorInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use DateInterval;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class SubscriptionEndsEventGenerator implements EventGeneratorInterface
{
    private $subscriptionsRepository;

    public function __construct(SubscriptionsRepository $subscriptionsRepository)
    {
        $this->subscriptionsRepository = $subscriptionsRepository;
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

        return array_map(function (IRow $subscriptionRow) {
            $parameters['subscription_id'] = $subscriptionRow->id;
            $payment = $subscriptionRow->related('payments')->limit(1)->fetch();
            if ($payment) {
                $parameters['payment_id'] = $payment->id;
            }

            return new BeforeEvent($subscriptionRow->id, $subscriptionRow->user_id, $parameters);
        }, $this->subscriptionsRepository->subscriptionsEndBetween($endTimeFrom, $endTimeTo)->fetchAll());
    }
}
