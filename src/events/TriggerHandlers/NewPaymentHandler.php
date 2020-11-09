<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class NewPaymentHandler implements HandlerInterface
{
    private $dispatcher;

    private $paymentsRepository;

    public function __construct(Dispatcher $dispatcher, PaymentsRepository $paymentsRepository)
    {
        $this->dispatcher = $dispatcher;
        $this->paymentsRepository = $paymentsRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['payment_id'])) {
            throw new \Exception('unable to handle event: payment_id missing');
        }
        $paymentId = $payload['payment_id'];
        $payment = $this->paymentsRepository->find($paymentId);

        if (!$payment) {
            throw new \Exception("unable to handle event: payment with ID=$paymentId does not exist");
        }

        $params = array_filter([
            'payment_id' => $payment->id,
            'subscription_id' => $payment->subscription_id ?? null
        ]);

        $this->dispatcher->dispatch('new_payment', $payment->user_id, $params);
        return true;
    }
}
