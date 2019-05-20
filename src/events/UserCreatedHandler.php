<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Repository\ScenariosRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class UserCreatedHandler implements HandlerInterface
{

    private $scenariosRepository;

    public function __construct(ScenariosRepository $scenariosRepository)
    {
        $this->scenariosRepository = $scenariosRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['user_id'])) {
            throw new \Exception('unable to handle event: user_id missing');
        }



        return true;
    }
}
