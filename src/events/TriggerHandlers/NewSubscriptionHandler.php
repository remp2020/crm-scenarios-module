<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class NewSubscriptionHandler implements HandlerInterface
{
    private $dispatcher;

    private $subscriptionsRepository;

    public function __construct(
        Dispatcher $dispatcher,
        SubscriptionsRepository $subscriptionsRepository
    ) {
        $this->dispatcher = $dispatcher;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['subscription_id'])) {
            throw new \Exception('unable to handle event: subscription_id missing');
        }
        $subscriptionId = $payload['subscription_id'];
        $subscription = $this->subscriptionsRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \Exception("unable to handle event: subscription with ID=$subscriptionId does not exist");
        }

        $params = ['subscription_id' => $payload['subscription_id']];
        $payment = $subscription->related('payments')->limit(1)->fetch();
        if ($payment) {
            $params['payment_id'] = $payment->id;
        }

        $this->dispatcher->dispatch('new_subscription', $subscription->user_id, $params);
        return true;
    }
}
