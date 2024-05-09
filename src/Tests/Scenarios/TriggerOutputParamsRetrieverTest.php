<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Models\Event\EventGeneratorOutputProviderInterface;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Crm\ScenariosModule\Engine\Validator\TriggerOutputParamsRetrieveException;
use Crm\ScenariosModule\Engine\Validator\TriggerOutputParamsRetriever;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use DateInterval;
use Exception;
use PHPUnit\Framework\TestCase;

class TriggerOutputParamsRetrieverTest extends TestCase
{
    public function testRetrieveFromEvent(): void
    {
        $triggerHandler = $this->createMock(TriggerHandlerInterface::class);
        $triggerHandler->expects($this->once())
            ->method('getOutputParams')
            ->willReturn(['first_input_param']);

        $triggerManager = $this->createMock(TriggerManager::class);
        $triggerManager
            ->expects($this->once())
            ->method('getTriggerHandlerByKey')
            ->with('some_event_code')
            ->willReturn($triggerHandler);

        $eventsStorage = $this->createMock(EventsStorage::class);

        $triggerOutputParamsRetriever = new TriggerOutputParamsRetriever($triggerManager, $eventsStorage);
        $outputParams = $triggerOutputParamsRetriever->retrieve([
            'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
            'event' => [
                'code' => 'some_event_code',
            ],
        ]);

        self::assertSame(['first_input_param'], $outputParams);
    }

    public function testRetrieveFromBeforeEvent(): void
    {
        $triggerManager = $this->createMock(TriggerManager::class);

        // PHPUnit 8.0 does not support creating a mocked objects with multiple interfaces anymore
        $eventGenerator = new class implements EventGeneratorInterface, EventGeneratorOutputProviderInterface {
            public function getOutputParams(): array
            {
                return ['first_input_param'];
            }

            public function generate(DateInterval $timeOffset): array
            {
                throw new Exception('Not implemented');
            }
        };

        $eventsStorage = $this->createMock(EventsStorage::class);
        $eventsStorage->expects($this->once())
            ->method('getEventGeneratorByCode')
            ->with('some_event_code')
            ->willReturn($eventGenerator);

        $triggerOutputParamsRetriever = new TriggerOutputParamsRetriever($triggerManager, $eventsStorage);
        $outputParams = $triggerOutputParamsRetriever->retrieve([
            'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
            'event' => [
                'code' => 'some_event_code',
            ],
        ]);

        self::assertSame(['first_input_param'], $outputParams);
    }

    public function testRetrieveFromUnknownTriggerType(): void
    {
        $this->expectException(TriggerOutputParamsRetrieveException::class);
        $this->expectExceptionMessage("Unknown trigger type 'unknown_trigger'.");

        $triggerManager = $this->createMock(TriggerManager::class);
        $eventsStorage = $this->createMock(EventsStorage::class);

        $triggerOutputParamsRetriever = new TriggerOutputParamsRetriever($triggerManager, $eventsStorage);
        $triggerOutputParamsRetriever->retrieve([
            'type' => 'unknown_trigger',
            'event' => [
                'code' => '',
            ],
        ]);
    }
}
