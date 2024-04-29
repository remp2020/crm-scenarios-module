<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Exception;

class TestUserTriggerHandler implements TriggerHandlerInterface
{
    public const EVENT_TYPE = 'scenarios-test-user';

    public function getName(): string
    {
        return 'Test user';
    }

    public function getKey(): string
    {
        return 'test_user';
    }

    public function getEventType(): string
    {
        return self::EVENT_TYPE;
    }

    public function getOutputParams(): array
    {
        return ['user_id'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['user_id'])) {
            throw new Exception("'user_id' is missing");
        }

        return new TriggerData($data['user_id'], [
            'user_id' => $data['user_id'],
        ]);
    }
}
