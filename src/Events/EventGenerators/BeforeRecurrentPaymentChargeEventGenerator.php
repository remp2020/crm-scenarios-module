<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Event\BeforeEvent;
use Crm\ApplicationModule\Event\EventGeneratorInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class BeforeRecurrentPaymentChargeEventGenerator implements EventGeneratorInterface
{
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

            return new BeforeEvent($recurrentPaymentRow->id, $recurrentPaymentRow->user_id, $parameters);
        }, $this->recurrentPaymentsRepository->activeFirstChargeBetween($endTimeFrom, $endTimeTo)->fetchAll());
    }
}
