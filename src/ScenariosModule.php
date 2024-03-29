<?php

namespace Crm\ScenariosModule;

use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Authorization\BearerTokenAuthorization;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\Application\CommandsContainerInterface;
use Crm\ApplicationModule\Application\Managers\AssetsManager;
use Crm\ApplicationModule\Application\Managers\SeederManager;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Models\Menu\MenuItem;
use Crm\ScenariosModule\Api\ScenariosCreateApiHandler;
use Crm\ScenariosModule\Api\ScenariosCriteriaHandler;
use Crm\ScenariosModule\Api\ScenariosInfoApiHandler;
use Crm\ScenariosModule\Api\ScenariosListGenericsApiHandler;
use Crm\ScenariosModule\Api\ScenariosStatsApiHandler;
use Crm\ScenariosModule\Commands\EventGeneratorCommand;
use Crm\ScenariosModule\Commands\ReconstructWaitEventsCommand;
use Crm\ScenariosModule\Commands\RemoveOldStatsDataCommand;
use Crm\ScenariosModule\Commands\ScenariosStatsAggregatorCommand;
use Crm\ScenariosModule\Commands\ScenariosWorkerCommand;
use Crm\ScenariosModule\Commands\TestUserCommand;
use Crm\ScenariosModule\Events\ABTestDistributeEventHandler;
use Crm\ScenariosModule\Events\ABTestElementUpdatedHandler;
use Crm\ScenariosModule\Events\AbTestElementUpdatedEvent;
use Crm\ScenariosModule\Events\ConditionCheckEventHandler;
use Crm\ScenariosModule\Events\EventGenerators\BeforeRecurrentPaymentChargeEventGenerator;
use Crm\ScenariosModule\Events\EventGenerators\SubscriptionEndsEventGenerator;
use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Events\OnboardingGoalsCheckEventHandler;
use Crm\ScenariosModule\Events\RunGenericEventHandler;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Events\SendEmailEventHandler;
use Crm\ScenariosModule\Events\SendPushNotificationEventHandler;
use Crm\ScenariosModule\Events\ShowBannerEventHandler;
use Crm\ScenariosModule\Events\TestUserEvent;
use Crm\ScenariosModule\Events\TriggerHandlers\AddressChangedHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\NewAddressHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\NewInvoiceHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\NewPaymentHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\NewSubscriptionHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\PaymentStatusChangeHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\RecurrentPaymentRenewedHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\RecurrentPaymentStateChangedHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\SubscriptionEndsHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\SubscriptionStartsHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\TestUserHandler;
use Crm\ScenariosModule\Events\TriggerHandlers\UserRegisteredHandler;
use Crm\ScenariosModule\Scenarios\HasPaymentCriteria;
use Crm\ScenariosModule\Seeders\SegmentGroupsSeeder;
use Tomaj\Hermes\Dispatcher;

class ScenariosModule extends CrmModule
{
    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem(
            $this->translator->translate('scenarios.admin.menu.default'),
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
            ScenariosInfoApiHandler::class,
            BearerTokenAuthorization::class
        ));
        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'create'),
            ScenariosCreateApiHandler::class,
            BearerTokenAuthorization::class
        ));
        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'criteria'),
            ScenariosCriteriaHandler::class,
            BearerTokenAuthorization::class
        ));

        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'generics'),
            ScenariosListGenericsApiHandler::class,
            BearerTokenAuthorization::class
        ));
        $apiRoutersContainer->attachRouter(new ApiRoute(
            new ApiIdentifier('1', 'scenarios', 'stats'),
            ScenariosStatsApiHandler::class,
            BearerTokenAuthorization::class
        ));
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(ScenariosWorkerCommand::class));
        $commandsContainer->registerCommand($this->getInstance(TestUserCommand::class));
        $commandsContainer->registerCommand($this->getInstance(EventGeneratorCommand::class));
        $commandsContainer->registerCommand($this->getInstance(ScenariosStatsAggregatorCommand::class));
        $commandsContainer->registerCommand($this->getInstance(RemoveOldStatsDataCommand::class));
        $commandsContainer->registerCommand($this->getInstance(ReconstructWaitEventsCommand::class));
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler('user-registered', $this->getInstance(UserRegisteredHandler::class));
        $dispatcher->registerHandler('new-subscription', $this->getInstance(NewSubscriptionHandler::class));
        $dispatcher->registerHandler('subscription-starts', $this->getInstance(SubscriptionStartsHandler::class));
        $dispatcher->registerHandler('subscription-ends', $this->getInstance(SubscriptionEndsHandler::class));
        $dispatcher->registerHandler('new-payment', $this->getInstance(NewPaymentHandler::class));
        $dispatcher->registerHandler('payment-status-change', $this->getInstance(PaymentStatusChangeHandler::class));
        $dispatcher->registerHandler('address-changed', $this->getInstance(AddressChangedHandler::class));
        $dispatcher->registerHandler('new-address', $this->getInstance(NewAddressHandler::class));
        $dispatcher->registerHandler('recurrent-payment-renewed', $this->getInstance(RecurrentPaymentRenewedHandler::class));
        $dispatcher->registerHandler('recurrent-payment-state-changed', $this->getInstance(RecurrentPaymentStateChangedHandler::class));
        $dispatcher->registerHandler(TestUserHandler::HERMES_MESSAGE_CODE, $this->getInstance(TestUserHandler::class));
        $dispatcher->registerHandler('new-invoice', $this->getInstance(NewInvoiceHandler::class));

        $dispatcher->registerHandler(SendEmailEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SendEmailEventHandler::class));
        $dispatcher->registerHandler(ShowBannerEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(ShowBannerEventHandler::class));
        $dispatcher->registerHandler(RunGenericEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(RunGenericEventHandler::class));
        $dispatcher->registerHandler(FinishWaitEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(FinishWaitEventHandler::class));
        $dispatcher->registerHandler(SegmentCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SegmentCheckEventHandler::class));
        $dispatcher->registerHandler(ConditionCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(ConditionCheckEventHandler::class));
        $dispatcher->registerHandler(OnboardingGoalsCheckEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(OnboardingGoalsCheckEventHandler::class));
        $dispatcher->registerHandler(SendPushNotificationEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(SendPushNotificationEventHandler::class));
        $dispatcher->registerHandler(ABTestDistributeEventHandler::HERMES_MESSAGE_CODE, $this->getInstance(ABTestDistributeEventHandler::class));
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('test_user', TestUserEvent::class, true);

        $eventsStorage->registerEventGenerator(SubscriptionEndsEventGenerator::BEFORE_EVENT_CODE, $this->getInstance(SubscriptionEndsEventGenerator::class));
        $eventsStorage->registerEventGenerator(BeforeRecurrentPaymentChargeEventGenerator::BEFORE_EVENT_CODE, $this->getInstance(BeforeRecurrentPaymentChargeEventGenerator::class));
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(AbTestElementUpdatedEvent::class, ABTestElementUpdatedHandler::class);
    }

    public function registerAssets(AssetsManager $assetsManager)
    {
        $assetsManager->copyAssets(__DIR__ . '/assets/scenariobuilder', 'layouts/admin/scenariobuilder');
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('trigger', HasPaymentCriteria::KEY, $this->getInstance(HasPaymentCriteria::class));
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(SegmentGroupsSeeder::class));
    }
}
