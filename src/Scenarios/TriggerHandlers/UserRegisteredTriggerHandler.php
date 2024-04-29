<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Exception;

class UserRegisteredTriggerHandler implements TriggerHandlerInterface
{
    public function getName(): string
    {
        return 'User registered';
    }

    public function getKey(): string
    {
        return 'user_registered';
    }

    public function getEventType(): string
    {
        return 'user-registered';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'password'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['user_id'])) {
            throw new Exception("'user_id' is missing");
        }

        if (!isset($data['password'])) {
            throw new Exception("'password' is missing");
        }

        return new TriggerData($data['user_id'], [
            'user_id' => $data['user_id'],
            'password' => $data['password']
        ]);
    }
}
