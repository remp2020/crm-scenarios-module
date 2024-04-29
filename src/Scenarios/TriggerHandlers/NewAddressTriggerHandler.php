<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\UsersModule\Repositories\AddressesRepository;
use Exception;

class NewAddressTriggerHandler implements TriggerHandlerInterface
{
    public function __construct(
        private readonly AddressesRepository $addressesRepository,
    ) {
    }

    public function getName(): string
    {
        return 'New address';
    }

    public function getKey(): string
    {
        return 'new_address';
    }

    public function getEventType(): string
    {
        return 'new-address';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'address_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['address_id'])) {
            throw new Exception("'address_id' is missing");
        }

        $addressId = $data['address_id'];
        $address = $this->addressesRepository->find($addressId);
        if (!$address) {
            throw new Exception(sprintf(
                "Address with ID=%s does not exist",
                $addressId
            ));
        }

        return new TriggerData($address->user_id, [
            'user_id' => $address->user_id,
            'address_id' => $address->id,
        ]);
    }
}
