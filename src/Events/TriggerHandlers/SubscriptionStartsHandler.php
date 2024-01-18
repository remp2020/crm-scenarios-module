<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class SubscriptionStartsHandler implements HandlerInterface
{
    public function __construct(
        private Dispatcher $dispatcher,
        private SubscriptionsRepository $subscriptionsRepository,
    ) {
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

        $this->dispatcher->dispatch('subscription_starts', $subscription->user_id, [
            'subscription_id' => $payload['subscription_id']
        ], [
            JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE => $message->getType()
        ]);

        return true;
    }
}
