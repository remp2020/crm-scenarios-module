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
use Crm\ScenariosModule\Api\ScenariosCriteriaHandler;
use Crm\ScenariosModule\Commands\ScenariosWorkerCommand;
use Crm\ScenariosModule\Commands\TestUserCommand;
use Crm\ScenariosModule\Events\ConditionCheckEventHandler;
use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\NewSubscriptionHandler;
use Crm\ScenariosModule\Events\OnboardingGoalsCheckEventHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\RecurrentPaymentRenewedHandler;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Events\SendEmailEventHandler;
use Crm\ScenariosModule\Events\ShowBannerEventHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\SubscriptionEndsHandler;
use Crm\ScenariosModule\Events\TestUserEvent;
use Crm\ScenariosModule\Events\TriggerHandlers\TestUserHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\UserCreatedHandler;
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

        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'criteria'),
            ScenariosCriteriaHandler::class,
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
        $dispatcher->registerHandler('new-subscription', $this->getInstance(NewSubscriptionHandler::class));
        $dispatcher->registerHandler('subscription-ends', $this->getInstance(SubscriptionEndsHandler::class));
        $dispatcher->registerHandler('recurrent-payment-renewed', $this->getInstance(RecurrentPaymentRenewedHandler::class));
        $dispatcher->registerHandler(TestUserHandler::HERMES_MESSAGE_CODE, $this->getInstance(TestUserHandler::class));

        $dispatcher->registerHandler(SendEmailEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SendEmailEventHandler::class));
        $dispatcher->registerHandler(ShowBannerEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(ShowBannerEventHandler::class));
        $dispatcher->registerHandler(FinishWaitEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(FinishWaitEventHandler::class));
        $dispatcher->registerHandler(SegmentCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SegmentCheckEventHandler::class));
        $dispatcher->registerHandler(ConditionCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(ConditionCheckEventHandler::class));
        $dispatcher->registerHandler(OnboardingGoalsCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(OnboardingGoalsCheckEventHandler::class));
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('test_user', TestUserEvent::class, true);
    }
}
