<?php

namespace Crm\ScenariosModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\IRow;

class BannerEvent extends AbstractEvent
{
    private $user;

    private $bannerId;

    private $expiresInMinutes;

    public function __construct(
        IRow $user,
        $bannerId,
        int $expiresInMinutes
    ) {
        $this->user = $user;
        $this->bannerId = $bannerId;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function getUser(): IRow
    {
        return $this->user;
    }

    public function getBannerId()
    {
        return $this->bannerId;
    }

    public function getExpiresInMinutes(): int
    {
        return $this->expiresInMinutes;
    }
}
