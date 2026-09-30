<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Engine;

use Crm\ApplicationModule\Models\Scenario\SensitiveTriggerParamsInterface;
use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Crm\ScenariosModule\Engine\Dispatcher as JobDispatcher;
use Crm\ScenariosModule\Engine\JobParamsRedactor;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Dispatcher;

class JobParamsRedactorTest extends TestCase
{
    private TriggerManager $triggerManager;
    private JobParamsRedactor $redactor;

    protected function setUp(): void
    {
        $this->triggerManager = new TriggerManager(
            $this->createStub(Dispatcher::class),
            $this->createStub(JobDispatcher::class),
        );
        $this->redactor = new JobParamsRedactor($this->triggerManager);
    }

    public function testDefaultParamsAreRedacted(): void
    {
        $this->assertSame(
            ['user_id' => 1, 'password' => JobParamsRedactor::REDACTED, 'pass' => JobParamsRedactor::REDACTED],
            $this->redactor->redact(['user_id' => 1, 'password' => 'secret', 'pass' => 'secret']),
        );
    }

    public function testTriggerDeclaredParamsAreRedacted(): void
    {
        $this->triggerManager->registerTriggerHandler($this->sensitiveTriggerHandler(['token']));

        $this->assertSame(
            ['user_id' => 1, 'token' => JobParamsRedactor::REDACTED],
            $this->redactor->redact(['user_id' => 1, 'token' => 'abc']),
        );
    }

    public function testNestedParamsAreRedacted(): void
    {
        $this->assertSame(
            ['data' => ['password' => JobParamsRedactor::REDACTED, 'x' => 1]],
            $this->redactor->redact(['data' => ['password' => 'secret', 'x' => 1]]),
        );
    }

    public function testRedactJsonKeepsUntouchedJson(): void
    {
        $json = '{ "user_id": 1, "empty": {} }';
        $this->assertSame($json, $this->redactor->redactJson($json));
    }

    private function sensitiveTriggerHandler(array $sensitiveParams): TriggerHandlerInterface
    {
        return new class($sensitiveParams) implements TriggerHandlerInterface, SensitiveTriggerParamsInterface {
            public function __construct(private readonly array $sensitiveParams)
            {
            }

            public function getName(): string
            {
                return 'Sensitive';
            }

            public function getKey(): string
            {
                return 'sensitive';
            }

            public function getEventType(): string
            {
                return 'sensitive';
            }

            public function getOutputParams(): array
            {
                return ['user_id', ...$this->sensitiveParams];
            }

            public function getSensitiveParams(): array
            {
                return $this->sensitiveParams;
            }

            public function handleEvent(array $data): TriggerData
            {
                return new TriggerData($data['user_id'], $data);
            }
        };
    }
}
