<?php

namespace Crm\ScenariosModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class AbTestElementUpdatedEvent extends AbstractEvent
{
    private $elementRow;

    private $segments;

    public function __construct(IRow $elementRow, array $segments)
    {
        $this->elementRow = $elementRow;
        $this->segments = $segments;
    }

    public function getElement(): IRow
    {
        return $this->elementRow;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }
}
