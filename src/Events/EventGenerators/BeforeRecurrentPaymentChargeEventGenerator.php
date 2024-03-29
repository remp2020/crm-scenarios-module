<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Models\Event\BeforeEvent;
use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class BeforeRecurrentPaymentChargeEventGenerator implements EventGeneratorInterface
{
    public const BEFORE_EVENT_CODE = 'before_recurrent_payment_charge';

    private RecurrentPaymentsRepository $recurrentPaymentsRepository;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function generate(DateInterval $timeOffset): array
    {
        $endTimeTo = new DateTime();
        $endTimeTo->add($timeOffset);

        $endTimeFrom = $endTimeTo->modifyClone("-30 minutes");

        return array_map(function (ActiveRow $recurrentPaymentRow) {
            $parameters['recurrent_payment_id'] = $recurrentPaymentRow->id;
            $parameters['subscription_type_id'] = $recurrentPaymentRow->subscription_type_id;
            $parameters['subscription_id'] = $recurrentPaymentRow->parent_payment->subscription_id ?? null;

            return new BeforeEvent($recurrentPaymentRow->id, $recurrentPaymentRow->user_id, $parameters);
        }, $this->recurrentPaymentsRepository->activeFirstChargeBetween($endTimeFrom, $endTimeTo)->fetchAll());
    }
}
