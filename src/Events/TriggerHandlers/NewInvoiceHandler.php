<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\InvoicesModule\Repositories\InvoicesRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class NewInvoiceHandler implements HandlerInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
        private InvoicesRepository $invoicesRepository,
        private PaymentsRepository $paymentsRepository,
    ) {
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['invoice_id'])) {
            throw new \Exception('unable to handle event: invoice_id missing');
        }

        $invoiceId = $payload['invoice_id'];
        $invoice = $this->invoicesRepository->find($invoiceId);

        if (!$invoice) {
            throw new \Exception("unable to handle event: invoice with ID={$invoiceId} does not exist");
        }

        // have to use invoice number to find invoice for payment instead of invoice_id
        // invoice_id is not set yet on payment
        $payment = $this->paymentsRepository->findBy('invoice_number_id', $invoice->invoice_number_id);
        if (!$payment) {
            throw new \Exception("unable to handle event: no payment related to invoice ID={$invoiceId}");
        }

        $params = array_filter([
            'invoice_id' => $invoiceId,
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id ?? null,
            'user_id' => $payment->user_id,
        ]);

        $this->dispatcher->dispatch('new_invoice', $payment->user_id, $params, [
            JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE => $message->getType()
        ]);
        return true;
    }
}
