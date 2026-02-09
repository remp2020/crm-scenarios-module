<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Scenarios\TriggerHandlers;

use Crm\ApplicationModule\Models\Scenario\TriggerData;
use Crm\ApplicationModule\Models\Scenario\TriggerHandlerInterface;
use Exception;

class UserConfirmedTriggerHandler implements TriggerHandlerInterface
{
    public function getName(): string
    {
        return 'User confirmed';
    }

    public function getKey(): string
    {
        return 'user_confirmed';
    }

    public function getEventType(): string
    {
        return 'user-confirmed';
    }

    public function getOutputParams(): array
    {
        return ['user_id', 'by_admin'];
    }

    public function handleEvent(array $data): TriggerData
    {
        if (!isset($data['user_id'])) {
            throw new Exception("'user_id' is missing");
        }

        if (!isset($data['by_admin'])) {
            throw new Exception("'by_admin' is missing");
        }

        return new TriggerData($data['user_id'], [
            'user_id' => $data['user_id'],
            'by_admin' => $data['by_admin'],
        ]);
    }
}
