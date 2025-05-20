<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Exception;

class RecurrentPaymentRenewedTriggerHandler implements TriggerHandlerInterface
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'Recurrent payment renewed';
    }

    public function getKey(): string
    {
        return 'recurrent_payment_renewed';
    }

    public function getEventType(): string
    {
        return 'recurrent-payment-renewed';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'recurrent_payment_id', 'payment_id', 'subscription_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['recurrent_payment_id'])) {
            throw new Exception("'recurrent_payment_id' is missing");
        }

        $recurrentPaymentId = $data['recurrent_payment_id'];
        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        if (!$recurrentPayment) {
            throw new Exception(sprintf(
                "Recurrent payment with ID=%s does not exist",
                $recurrentPaymentId,
            ));
        }
        if ($recurrentPayment->payment_id === null) {
            throw new Exception(sprintf(
                "Recurrent payment with ID=%s has no payment assigned",
                $recurrentPaymentId,
            ));
        }

        return new TriggerData($recurrentPayment->user_id, [
            'user_id' => $recurrentPayment->user_id,
            'recurrent_payment_id' => $recurrentPayment->id,
            'payment_id' => $recurrentPayment->payment_id,
            'subscription_id' => $recurrentPayment->payment?->subscription_id ?? null,
        ]);
    }
}
