<?php

namespace Crm\ScenariosModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class AbTestElementUpdatedEvent extends AbstractEvent
{
    private $elementRow;

    private $segments;

    public function __construct(ActiveRow $elementRow, array $segments)
    {
        $this->elementRow = $elementRow;
        $this->segments = $segments;
    }

    public function getElement(): ActiveRow
    {
        return $this->elementRow;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }
}
