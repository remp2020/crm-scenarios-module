<?php

namespace Crm\ScenariosModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ScenariosModule\Commands\ScenariosWorkerCommand;
use Crm\ScenariosModule\Commands\TestUserCommand;
use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Events\SendEmailEventHandler;
use Crm\ScenariosModule\Events\TestUserEvent;
use Crm\ScenariosModule\Events\TestUserHandler;
use Crm\ScenariosModule\Events\UserCreatedHandler;
use Tomaj\Hermes\Dispatcher;

class ScenariosModule extends CrmModule
{
    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            '',
            ':Scenarios:ScenariosAdmin:default',
            'fa fa-code-branch',
            780
        );

        $menuContainer->attachMenuItem($mainMenu);
    }

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

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(ScenariosWorkerCommand::class));
        $commandsContainer->registerCommand($this->getInstance(TestUserCommand::class));
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler('user-created', $this->getInstance(UserCreatedHandler::class));
        $dispatcher->registerHandler(TestUserHandler::HERMES_MESSAGE_CODE, $this->getInstance(TestUserHandler::class));

        $dispatcher->registerHandler(SendEmailEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SendEmailEventHandler::class));
        $dispatcher->registerHandler(FinishWaitEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(FinishWaitEventHandler::class));
        $dispatcher->registerHandler(SegmentCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SegmentCheckEventHandler::class));
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('test_user', TestUserEvent::class, true);
    }
}
