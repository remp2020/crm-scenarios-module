<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Engine\ScenarioStarter;
use Crm\ScenariosModule\Engine\TriggersDispatcher;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class UserCreatedHandler implements HandlerInterface
{
    private $triggersDispatcher;

    public function __construct(TriggersDispatcher $triggersDispatcher)
    {
        $this->triggersDispatcher = $triggersDispatcher;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['user_id'])) {
            throw new \Exception('unable to handle event: user_id missing');
        }

        $this->triggersDispatcher->dispatch('user_created', $payload['user_id']);
        return true;
    }
}
