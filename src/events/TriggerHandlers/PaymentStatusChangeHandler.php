<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class PaymentStatusChangeHandler implements HandlerInterface
{
    private $dispatcher;

    private $paymentsRepository;

    public function __construct(
        Dispatcher $dispatcher,
        PaymentsRepository $paymentsRepository
    ) {
        $this->dispatcher = $dispatcher;
        $this->paymentsRepository = $paymentsRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['payment_id'])) {
            throw new \Exception('unable to handle event: payment_id missing');
        }

        $sendEmail = $payload['send_email'] ?? true;
        if (!$sendEmail) {
            // in such case, do not trigger any scenario
            return true;
        }

        $paymentId = $payload['payment_id'];
        $payment = $this->paymentsRepository->find($paymentId);

        if (!$payment) {
            throw new \Exception("unable to handle event: payment with ID=$paymentId does not exist");
        }

        $params = array_filter([
            'payment_id' => $paymentId,
            'subscription_id' => $payment->subscription_id
        ]);

        $this->dispatcher->dispatch('payment_change_status', $payment->user_id, $params);
        return true;
    }
}
