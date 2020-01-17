<?php

namespace Crm\ScenariosModule\Tests;

use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class TestNotificationHandler extends AbstractListener
{
    private $sentMailCodes = [];

    public function handle(EventInterface $event)
    {
        if (!($event instanceof NotificationEvent)) {
            throw new \Exception('Unable to handle event, expected NotificationEvent');
        }

        $email = $event->getUser()->email;

        if (!array_key_exists($email, $this->sentMailCodes)) {
            $this->sentMailCodes[$email] = [];
        }

        $this->sentMailCodes[$email][] = $event->getTemplateCode();
    }

    public function getMailTemplateCodesSentTo(string $email): array
    {
        return $this->sentMailCodes[$email] ?? [];
    }
}
