<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\UsersModule\Events\NotificationContext;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

trait NotificationContextTrait
{
    public function getNotificationContext(ActiveRow $job): ?NotificationContext
    {
        $notificationContextData = [];
        if ($job->context) {
            $jobContext = Json::decode($job->context, Json::FORCE_ARRAY);
            $contextMessageHermesType = $jobContext[JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE] ?? null;
            $notificationContextData[NotificationContext::HERMES_MESSAGE_TYPE] = $contextMessageHermesType;
        }
        return new NotificationContext($notificationContextData);
    }
}
