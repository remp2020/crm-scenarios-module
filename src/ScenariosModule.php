<?php

namespace Crm\ScenariosModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ScenariosModule\Events\ScenarioChangedEvent;
use Crm\ScenariosModule\Events\ScenarioChangedHandler;
use League\Event\Emitter;

class ScenariosModule extends CrmModule
{
    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'info'),
            \Crm\ScenariosModule\Api\ScenariosInfoApiHandler::class,
            \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
        ));
        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'create'),
            \Crm\ScenariosModule\Api\ScenariosCreateApiHandler::class,
            \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
        ));
        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'element'),
            \Crm\ScenariosModule\Api\ScenariosElementApiHandler::class,
            \Crm\ApiModule\Authorization\BearerTokenAuthorization::class
        ));
    }

    //public function registerEvents(EventsStorage $eventsStorage)
    //{
    //    $eventsStorage->register('scenario_changed', Events\ScenarioChangedEvent::class);
    //}

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            ScenarioChangedEvent::class,
            $this->getInstance(ScenarioChangedHandler::class)
        );
    }
}
