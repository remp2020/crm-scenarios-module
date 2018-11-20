<?php

namespace Crm\ScenariosModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\SeederManager;
use Crm\ScenariosModule\Seeders\ScenariosSeeder;

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
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ScenariosSeeder::class));
    }
}
