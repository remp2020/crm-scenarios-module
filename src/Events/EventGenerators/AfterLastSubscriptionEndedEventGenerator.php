<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Models\Event\BeforeEvent;
use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Models\Event\EventGeneratorOutputProviderInterface;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateInterval;
use Nette\Database\Row;
use Nette\Utils\DateTime;

class AfterLastSubscriptionEndedEventGenerator implements EventGeneratorInterface, EventGeneratorOutputProviderInterface
{
    use NowTrait;

    public const BEFORE_EVENT_CODE = 'after_last_subscription_ended';

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
    ) {
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'subscription_id', 'subscription_type_id'];
    }

    public function generate(DateInterval $timeOffset): array
    {
        $lastEndTimeTo = DateTime::from($this->getNow());
        $lastEndTimeTo->sub($timeOffset);
        $lastEndTimeFrom = (clone $lastEndTimeTo)->sub(new DateInterval("PT30M"));

        return array_map(function (Row $lastSubscriptionRow) {
            $parameters['user_id'] = $lastSubscriptionRow['user_id'];
            $parameters['subscription_id'] = $lastSubscriptionRow['id'];
            $parameters['subscription_type_id'] = $lastSubscriptionRow['subscription_type_id'];

            return new BeforeEvent($lastSubscriptionRow['id'], $lastSubscriptionRow['user_id'], $parameters);
        }, $this->getLastSubscriptions($lastEndTimeFrom, $lastEndTimeTo));
    }

    private function getLastSubscriptions(DateTime $lastEndTimeFrom, DateTime $lastEndTimeTo): array
    {
        $subscriptionsEndedInInterval = $this->subscriptionsRepository->getTable()->query(
            <<<SQL
                SELECT `subscriptions`.*
                FROM `subscriptions`
                LEFT JOIN `subscriptions` AS `subscriptions_ending_later`
                  ON `subscriptions`.`user_id` = `subscriptions_ending_later`.`user_id`
                  AND `subscriptions_ending_later`.`end_time` > `subscriptions`.`end_time`
                WHERE
                  -- no next subscription
                  `subscriptions_ending_later`.`id` IS NULL
                  -- ended in specific period
                  AND `subscriptions`.`end_time` >= '{$lastEndTimeFrom}'
                  AND `subscriptions`.`end_time` <= '{$lastEndTimeTo}'
                ;
            SQL
        )->fetchAll();

        return $subscriptionsEndedInInterval;
    }
}
