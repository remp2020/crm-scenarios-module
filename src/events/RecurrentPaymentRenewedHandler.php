<?php

namespace Crm\ScenariosModule\Events;

use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class RecurrentPaymentRenewedHandler implements HandlerInterface
{
    private $dispatcher;

    private $recurrentPaymentsRepository;

    public function __construct(Dispatcher $dispatcher, RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->dispatcher = $dispatcher;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['recurrent_payment_id'])) {
            throw new \Exception('unable to handle event: recurrent_payment_id missing');
        }
        $recurrentPaymentId = $payload['recurrent_payment_id'];
        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);

        if (!$recurrentPayment) {
            throw new \Exception("unable to handle event: recurrent payment with ID=$recurrentPaymentId does not exist");
        }

        $this->dispatcher->dispatch('recurrent_payment_renewed', $recurrentPayment->user_id, [
            'recurrent_payment_id' => $recurrentPaymentId,
            'payment_id' => $recurrentPayment->payment_id
        ]);
        return true;
    }
}
