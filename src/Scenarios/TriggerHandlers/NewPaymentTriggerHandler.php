<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Exception;

class NewPaymentTriggerHandler implements TriggerHandlerInterface
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'New payment';
    }

    public function getKey(): string
    {
        return 'new_payment';
    }

    public function getEventType(): string
    {
        return 'new-payment';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'payment_id', 'subscription_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['payment_id'])) {
            throw new Exception("'payment_id' is missing");
        }

        $paymentId = $data['payment_id'];
        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new Exception(sprintf(
                "Payment with ID=%s does not exist",
                $paymentId
            ));
        }

        $payload = [
            'user_id' => $payment->user_id,
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id ?? null,
        ];

        return new TriggerData($payment->user_id, $payload);
    }
}
