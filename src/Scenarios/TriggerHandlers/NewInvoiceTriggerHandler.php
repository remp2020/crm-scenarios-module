<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Exception;

class NewInvoiceTriggerHandler implements TriggerHandlerInterface
{
    public function __construct(
        private readonly InvoicesRepository $invoicesRepository,
        private readonly PaymentsRepository $paymentsRepository,
    ) {
    }

    public function getName(): string
    {
        return 'New invoice';
    }

    public function getKey(): string
    {
        return 'new_invoice';
    }

    public function getEventType(): string
    {
        return 'new-invoice';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'invoice_id', 'payment_id', 'subscription_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['invoice_id'])) {
            throw new Exception("'invoice_id' is missing");
        }

        $invoiceId = $data['invoice_id'];
        $invoice = $this->invoicesRepository->find($invoiceId);
        if (!$invoice) {
            throw new Exception(sprintf(
                "Invoice with ID=%s does not exist",
                $invoiceId
            ));
        }

        $payment = $this->paymentsRepository->findBy('invoice_number_id', $invoice->invoice_number_id);
        if (!$payment) {
            throw new Exception(sprintf(
                "No payment related to invoice ID=%s",
                $invoiceId
            ));
        }

        $payload = [
            'user_id' => $payment->user_id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id ?? null,
        ];

        return new TriggerData($payment->user_id, $payload);
    }
}
