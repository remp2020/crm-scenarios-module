<?php

namespace Crm\ScenariosModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class BannerEvent extends AbstractEvent
{
    private $user;

    private $bannerId;

    private $expiresInMinutes;

    public function __construct(
        ActiveRow $user,
        $bannerId,
        int $expiresInMinutes
    ) {
        $this->user = $user;
        $this->bannerId = $bannerId;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function getUser(): ActiveRow
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
