<?php

namespace Crm\ScenariosModule\Events;

use League\Event\EventInterface;

/**
 * This interfaces serves for adding additional parameters
 * to running scenario jobs, through scenario generic actions.
 *
 * Additional parameters can then be used in following elements of scenario.
 *
 * If job parameters already contain params with same name. Parameter will be ignored
 * and debug warning will be logged. Handled in: RunGenericEventHandler.
 */
interface ScenarioGenericEventAdditionalParamsInterface extends EventInterface
{
    /**
     * Get additional params
     *
     * @return array
     */
    public function getAdditionalJobParameters(): array;

    /**
     * Sets additional params array to event
     *
     * @param array $params = ['param_name' => 'param_value]
     * @return void
     */
    public function setAdditionalJobParameters(array $params): void;
}
