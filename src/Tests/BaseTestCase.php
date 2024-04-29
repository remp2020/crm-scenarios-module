<?php

namespace Crm\ScenariosModule\Tests;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\InvoicesModule\Events\NewInvoiceEvent;
use Crm\OnboardingModule\Repositories\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repositories\UserOnboardingGoalsRepository;
use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\Events\NewPaymentEvent;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStateChangedEvent;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\PrintModule\Seeders\AddressTypesSeeder;
use Crm\ScenariosModule\Engine\Engine;
use Crm\ScenariosModule\Repositories\ElementElementsRepository;
use Crm\ScenariosModule\Repositories\ElementStatsRepository;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggerElementsRepository;
use Crm\ScenariosModule\Repositories\TriggerStatsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\ScenariosModule\ScenariosModule;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\UserRegisteredEvent;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Tests\TestNotificationHandler;
use Tomaj\Hermes\Dispatcher;

abstract class BaseTestCase extends DatabaseTestCase
{
    /** @var ScenariosModule */
    protected $scenariosModule;

    /** @var Dispatcher */
    protected $dispatcher;

    /** @var LazyEventEmitter */
    protected $lazyEventEmitter;

    /** @var Engine */
    protected $engine;

    /** @var TestNotificationHandler */
    protected $testNotificationHandler;

    /** @var EventsStorage */
    protected $eventsStorage;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            // To work with subscriptions, we need all these tables
            SubscriptionsRepository::class,
            SubscriptionMetaRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            // Segment tables
            SegmentGroupsRepository::class,
            SegmentsRepository::class,
            // Scenario tables
            JobsRepository::class,
            ScenariosRepository::class,
            TriggerElementsRepository::class,
            TriggersRepository::class,
            ElementElementsRepository::class,
            ElementsRepository::class,
            // Scenario stats
            ElementStatsRepository::class,
            TriggerStatsRepository::class,
            // Payments + recurrent payments
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            RecurrentPaymentsRepository::class,
            // User goals
            OnboardingGoalsRepository::class,
            UserOnboardingGoalsRepository::class,
            // Content access
            ContentAccessRepository::class,
            SubscriptionTypeContentAccessRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
            CountriesSeeder::class,
            AddressTypesSeeder::class,
            \Crm\InvoicesModule\Seeders\AddressTypesSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();

        // INITIALIZE MODULES
        // TODO: figure out how to do this in configuration
        $translator = $this->inject(Translator::class);
        $this->scenariosModule = new ScenariosModule($this->container, $translator);
        $this->dispatcher = $this->inject(Dispatcher::class);
        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);

        $this->testNotificationHandler = new TestNotificationHandler();

        // Events are not automatically registered, we need to register them manually for tests
        $this->eventsStorage = $this->inject(EventsStorage::class);
        $this->eventsStorage->register('user_registered', UserRegisteredEvent::class, true);
        $this->eventsStorage->register('new_payment', NewPaymentEvent::class, true);
        $this->eventsStorage->register('payment_change_status', PaymentChangeStatusEvent::class, true);
        $this->eventsStorage->register('new_subscription', NewSubscriptionEvent::class, true);
        $this->eventsStorage->register('subscription_starts', SubscriptionStartsEvent::class, true);
        $this->eventsStorage->register('subscription_ends', SubscriptionEndsEvent::class, true);
        $this->eventsStorage->register('recurrent_payment_renewed', RecurrentPaymentRenewedEvent::class, true);
        $this->eventsStorage->register('recurrent_payment_state_changed', RecurrentPaymentStateChangedEvent::class, true);
        $this->eventsStorage->register('before_recurrent_payment_charge', BeforeRecurrentPaymentChargeEvent::class, true);
        $this->eventsStorage->register('new_invoice', NewInvoiceEvent::class, true);
        $this->scenariosModule->registerHermesHandlers($this->dispatcher);
        $this->scenariosModule->registerScenariosTriggers($this->inject(TriggerManager::class));

        // Email notification is going to be handled by test handler
        $this->lazyEventEmitter->addListener(NotificationEvent::class, $this->testNotificationHandler);

        $this->engine = $this->inject(Engine::class);
    }

    protected function tearDown(): void
    {
        $this->lazyEventEmitter->removeAllListeners(NotificationEvent::class);

        parent::tearDown();
    }

    protected function elementId($uuid)
    {
        /** @var ElementsRepository $er */
        $er = $this->getRepository(ElementsRepository::class);
        return $er->findByUuid($uuid)->id;
    }

    protected function triggerId($uuid)
    {
        return $this->getRepository(TriggersRepository::class)->findByUuid($uuid)->id;
    }

    public static function obj(array $array)
    {
        return json_decode(json_encode($array), false);
    }

    /**
     * Returns list of email template codes sent to given $email
     * @param $email
     *
     * @return string[]
     */
    public function mailsSentTo($email): array
    {
        $events = $this->testNotificationHandler->notificationsSentTo($email);
        return array_map(function ($event) {
            return $event->getTemplateCode();
        }, $events);
    }

    /**
     * Returns list of parameters attached to NotificationEvent
     *
     * @param string $email - Email address of recipient.
     * @param string $templateCode - Email's template_code which should be used
     *                               by Mailer.
     * @return array|null - Returns null if combination of email & template_code
     *                      are not found within handled notifications.
     */
    public function notificationEmailParams(string $email, string $templateCode): ?array
    {
        $events = $this->testNotificationHandler->notificationsSentTo($email);
        foreach ($events as $event) {
            if ($event->getTemplateCode() === $templateCode) {
                return $event->getParams();
            }
        }
        return null;
    }
}
