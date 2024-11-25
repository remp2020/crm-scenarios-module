<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Models\Event\BeforeEvent;
use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Models\Event\EventGeneratorOutputProviderInterface;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class BeforeRecurrentPaymentChargeEventGenerator implements EventGeneratorInterface, EventGeneratorOutputProviderInterface
{
    public const BEFORE_EVENT_CODE = 'before_recurrent_payment_charge';

    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly RecurrentPaymentsResolver $recurrentPaymentsResolver,
    ) {
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'recurrent_payment_id', 'subscription_type_id', 'subscription_id'];
    }

    public function generate(DateInterval $timeOffset): array
    {
        $endTimeTo = new DateTime();
        $endTimeTo->add($timeOffset);

        $endTimeFrom = $endTimeTo->modifyClone("-30 minutes");

        return array_map(function (ActiveRow $recurrentPaymentRow) {
            $parameters['user_id'] = $recurrentPaymentRow->user_id;
            $parameters['recurrent_payment_id'] = $recurrentPaymentRow->id;
            $parameters['subscription_type_id'] = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPaymentRow);
            $parameters['subscription_id'] = $recurrentPaymentRow->parent_payment->subscription_id ?? null;

            return new BeforeEvent($recurrentPaymentRow->id, $recurrentPaymentRow->user_id, $parameters);
        }, $this->recurrentPaymentsRepository->activeFirstChargeBetween($endTimeFrom, $endTimeTo)->fetchAll());
    }
}
