<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\ScenariosModule\Scenarios\TriggerHandlers\UserConfirmedTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Exception;

class UserConfirmedTriggerHandlerTest extends BaseTestCase
{
    public function testKey(): void
    {
        /** @var UserConfirmedTriggerHandler $userConfirmedTriggerHandler */
        $userConfirmedTriggerHandler = $this->inject(UserConfirmedTriggerHandler::class);
        $this->assertSame('user_confirmed', $userConfirmedTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var UserConfirmedTriggerHandler $userConfirmedTriggerHandler */
        $userConfirmedTriggerHandler = $this->inject(UserConfirmedTriggerHandler::class);
        $this->assertSame('user-confirmed', $userConfirmedTriggerHandler->getEventType());
    }

    public function testHandleEvent(): void
    {
        /** @var UserConfirmedTriggerHandler $userConfirmedTriggerHandler */
        $userConfirmedTriggerHandler = $this->inject(UserConfirmedTriggerHandler::class);
        $triggerData = $userConfirmedTriggerHandler->handleEvent([
            'user_id' => 1,
            'by_admin' => false,
        ]);

        $this->assertSame(1, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $userConfirmedTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => 1,
            'by_admin' => false,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingUserId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'user_id' is missing");

        /** @var UserConfirmedTriggerHandler $userConfirmedTriggerHandler */
        $userConfirmedTriggerHandler = $this->inject(UserConfirmedTriggerHandler::class);
        $userConfirmedTriggerHandler->handleEvent([
            'by_admin' => false,
        ]);
    }

    public function testHandleEventMissingByAdmin(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'by_admin' is missing");

        /** @var UserConfirmedTriggerHandler $userConfirmedTriggerHandler */
        $userConfirmedTriggerHandler = $this->inject(UserConfirmedTriggerHandler::class);
        $userConfirmedTriggerHandler->handleEvent([
            'user_id' => 1,
        ]);
    }
}
