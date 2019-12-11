<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\ScenariosModule\Engine\Dispatcher;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class UserCreatedHandler implements HandlerInterface
{
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['user_id'])) {
            throw new \Exception('unable to handle event: user_id missing');
        }
        if (!isset($payload['password'])) {
            throw new \Exception('unable to handle event: password missing');
        }

        $this->dispatcher->dispatch('user_created', $payload['user_id'], [
            'password' => $payload['password']
        ]);
        return true;
    }
}
