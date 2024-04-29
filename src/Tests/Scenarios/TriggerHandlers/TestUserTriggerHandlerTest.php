<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\TriggerHandlers;

use Crm\ScenariosModule\Scenarios\TriggerHandlers\TestUserTriggerHandler;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Exception;

class TestUserTriggerHandlerTest extends BaseTestCase
{
    public function testKey(): void
    {
        /** @var TestUserTriggerHandler $testUserTriggerHandler */
        $testUserTriggerHandler = $this->inject(TestUserTriggerHandler::class);
        $this->assertSame('test_user', $testUserTriggerHandler->getKey());
    }

    public function testEventType(): void
    {
        /** @var TestUserTriggerHandler $testUserTriggerHandler */
        $testUserTriggerHandler = $this->inject(TestUserTriggerHandler::class);
        $this->assertSame('scenarios-test-user', $testUserTriggerHandler::EVENT_TYPE);
        $this->assertSame('scenarios-test-user', $testUserTriggerHandler->getEventType());
    }

    public function testHandleEvent(): void
    {
        /** @var TestUserTriggerHandler $testUserTriggerHandler */
        $testUserTriggerHandler = $this->inject(TestUserTriggerHandler::class);
        $triggerData = $testUserTriggerHandler->handleEvent([
            'user_id' => 1,
        ]);

        $this->assertSame(1, $triggerData->userId);
        $this->assertSame(array_keys($triggerData->payload), $testUserTriggerHandler->getOutputParams());
        $this->assertSame([
            'user_id' => 1,
        ], $triggerData->payload);
    }

    public function testHandleEventMissingUserId(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'user_id' is missing");

        /** @var TestUserTriggerHandler $testUserTriggerHandler */
        $testUserTriggerHandler = $this->inject(TestUserTriggerHandler::class);
        $testUserTriggerHandler->handleEvent([]);
    }
}
