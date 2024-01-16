<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repositories\JobsRepository;
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

        $params = array_filter([
            'recurrent_payment_id' => $recurrentPaymentId,
            'payment_id' => $recurrentPayment->payment_id,
            'subscription_id' => $recurrentPayment->payment->subscription_id ?? null,
        ]);

        $this->dispatcher->dispatch('recurrent_payment_renewed', $recurrentPayment->user_id, $params, [
            JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE => $message->getType()
        ]);
        return true;
    }
}
