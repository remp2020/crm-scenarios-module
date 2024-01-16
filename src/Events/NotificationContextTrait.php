<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Repositories\JobsRepository;
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

            $contextBeforeEvent = $jobContext[JobsRepository::CONTEXT_BEFORE_EVENT] ?? null;
            $notificationContextData[NotificationContext::BEFORE_EVENT] = $contextBeforeEvent;
        }
        return new NotificationContext($notificationContextData);
    }
}
