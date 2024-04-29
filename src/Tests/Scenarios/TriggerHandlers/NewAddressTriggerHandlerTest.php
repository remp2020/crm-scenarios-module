<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\DenniknModule\Seeders\AddressTypesSeeder;
use Crm\ScenariosModule\Scenarios\TriggerHandlers\NewAddressTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Exception;

class NewAddressTriggerHandlerTest extends BaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            AddressesRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            ...parent::requiredSeeders(),
            AddressTypesSeeder::class,
        ];
    }

    public function testKey(): void
    {
        /** @var NewAddressTriggerHandler $newAddressTriggerHandler */
        $newAddressTriggerHandler = $this->inject(NewAddressTriggerHandler::class);
        $this->assertSame('new_address', $newAddressTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var NewAddressTriggerHandler $newAddressTriggerHandler */
        $newAddressTriggerHandler = $this->inject(NewAddressTriggerHandler::class);
        $this->assertSame('new-address', $newAddressTriggerHandler->getEventType());
    }

    public function testHandleEvent(): void
    {
        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        /** @var AddressesRepository $addressesRepository */
        $addressesRepository = $this->getRepository(AddressesRepository::class);
        $address = $addressesRepository->add(
            $user,
            AddressTypesSeeder::CROWDFUNDING_SOCIAL_NETWORKS,
            firstName: null,
            lastName: null,
            address: null,
            number: null,
            city: null,
            zip: null,
            countryId: null,
            phoneNumber: null
        );

        /** @var NewAddressTriggerHandler $newAddressTriggerHandler */
        $newAddressTriggerHandler = $this->inject(NewAddressTriggerHandler::class);
        $triggerData = $newAddressTriggerHandler->handleEvent([
            'address_id' => $address->id,
        ]);

        $this->assertSame($user->id, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $newAddressTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingAddressId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'address_id' is missing");

        /** @var NewAddressTriggerHandler $newAddressTriggerHandler */
        $newAddressTriggerHandler = $this->inject(NewAddressTriggerHandler::class);
        $newAddressTriggerHandler->handleEvent([]);
    }

    public function testHandleEventMissingAddress(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Address with ID=1 does not exist");

        /** @var NewAddressTriggerHandler $newAddressTriggerHandler */
        $newAddressTriggerHandler = $this->inject(NewAddressTriggerHandler::class);
        $newAddressTriggerHandler->handleEvent([
            'address_id' => 1,
        ]);
    }
}
