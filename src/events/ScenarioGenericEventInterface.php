<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Criteria\Params\BooleanParam;
use Crm\ApplicationModule\Criteria\Params\NumberParam;
use Crm\ApplicationModule\Criteria\Params\StringLabeledArrayParam;
use League\Event\EventInterface;

interface ScenarioGenericEventInterface
{
    /**
     * Label used as name of generic event in ui selection inputs.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Returns an array of CriteriaParam definitions available for the generic event
     *
     *
     * @return BooleanParam|NumberParam|StringLabeledArrayParam[]
     */
    public function getParams(): array;

    /**
     * Return new League event to emit when generic action is triggered.
     *
     * @param object $options additional settings (set in ui) passed to emitted event
     * @param object $params info about triggered action such as user_id, payment or subscription information
     * @return EventInterface
     */
    public function createEvent($options, $params): EventInterface;
}
