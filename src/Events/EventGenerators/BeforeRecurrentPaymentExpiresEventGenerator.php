<?php

namespace Crm\ScenariosModule\Events\EventGenerators;

use Crm\ApplicationModule\Models\Event\BeforeEvent;
use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Models\Event\EventGeneratorOutputProviderInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class BeforeRecurrentPaymentExpiresEventGenerator implements EventGeneratorInterface, EventGeneratorOutputProviderInterface
{
    public const BEFORE_EVENT_CODE = 'before_recurrent_payment_expires';

    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
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
            $parameters['subscription_type_id'] = $recurrentPaymentRow->subscription_type_id;
            $parameters['subscription_id'] = $recurrentPaymentRow->parent_payment->subscription_id ?? null;

            return new BeforeEvent($recurrentPaymentRow->id, $recurrentPaymentRow->user_id, $parameters);
        }, $this->getExpiringRecurrentPayments($endTimeFrom, $endTimeTo));
    }

    private function getExpiringRecurrentPayments(DateTime $expiresFrom, DateTime $expiresTo): array
    {
        return $this->recurrentPaymentsRepository->getTable()
            ->where([
                'state' => RecurrentPaymentStateEnum::Active->value,
                'expires_at >=' => $expiresFrom,
                'expires_at <=' => $expiresTo,
                'parent_payment.status' => [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value]
            ])->fetchAll();
    }
}
