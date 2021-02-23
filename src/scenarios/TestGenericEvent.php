<?php

namespace Crm\ScenariosModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use League\Event\AbstractEvent;
use League\Event\EventInterface;

class TestGenericEvent implements ScenarioGenericEventInterface
{
    public function getLabel(): string
    {
        return 'Test generic event';
    }

    public function getParams(): array
    {
        return [
            new BooleanParam('bool_param', 'Boolean param'),
            new NumberParam('number_param', 'Number param', 'unit'),
            new StringLabeledArrayParam('labeled_string_param', 'Labeled string param', [
                'abc' => 'Abc'
            ]),
        ];
    }

    public function createEvent($options, $params): EventInterface
    {
        return new class extends AbstractEvent {
        };
    }
}
