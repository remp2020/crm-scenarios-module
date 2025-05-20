<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\ScenariosModule\Scenarios\TriggerHandlers\UserRegisteredTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Exception;

class UserRegisteredTriggerHandlerTest extends BaseTestCase
{
    public function testKey(): void
    {
        /** @var UserRegisteredTriggerHandler $userRegisteredTriggerHandler */
        $userRegisteredTriggerHandler = $this->inject(UserRegisteredTriggerHandler::class);
        $this->assertSame('user_registered', $userRegisteredTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var UserRegisteredTriggerHandler $userRegisteredTriggerHandler */
        $userRegisteredTriggerHandler = $this->inject(UserRegisteredTriggerHandler::class);
        $this->assertSame('user-registered', $userRegisteredTriggerHandler->getEventType());
    }

    public function testHandleEvent(): void
    {
        /** @var UserRegisteredTriggerHandler $userRegisteredTriggerHandler */
        $userRegisteredTriggerHandler = $this->inject(UserRegisteredTriggerHandler::class);
        $triggerData = $userRegisteredTriggerHandler->handleEvent([
            'user_id' => 1,
            'password' => 'password',
        ]);

        $this->assertSame(1, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $userRegisteredTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => 1,
            'password' => 'password',
        ], $triggerData->payload);
    }

    public function testHandleEventMissingUserId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'user_id' is missing");

        /** @var UserRegisteredTriggerHandler $userRegisteredTriggerHandler */
        $userRegisteredTriggerHandler = $this->inject(UserRegisteredTriggerHandler::class);
        $userRegisteredTriggerHandler->handleEvent([
            'password' => 'password',
        ]);
    }

    public function testHandleEventMissingPassword(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'password' is missing");

        /** @var UserRegisteredTriggerHandler $userRegisteredTriggerHandler */
        $userRegisteredTriggerHandler = $this->inject(UserRegisteredTriggerHandler::class);
        $userRegisteredTriggerHandler->handleEvent([
            'user_id' => 1,
        ]);
    }
}
