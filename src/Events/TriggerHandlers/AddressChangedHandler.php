<?php

namespace Crm\ScenariosModule\Events\TriggerHandlers;

use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class AddressChangedHandler implements HandlerInterface
{
    private $dispatcher;

    private $addressesRepository;

    public function __construct(
        Dispatcher $dispatcher,
        AddressesRepository $addressesRepository
    ) {
        $this->dispatcher = $dispatcher;
        $this->addressesRepository = $addressesRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!isset($payload['address_id'])) {
            throw new \Exception('unable to handle event: address_id missing');
        }

        $addressId = $payload['address_id'];
        $address = $this->addressesRepository->find($payload['address_id']);

        if (!$address) {
            throw new \Exception("unable to handle event: address with ID=$addressId does not exist");
        }

        $params = array_filter([
            'address_id' => $address->id,
            'user_id' => $address->user_id,
        ]);

        $this->dispatcher->dispatch('address_changed', $address->user_id, $params, [
            JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE => $message->getType()
        ]);
        return true;
    }
}
