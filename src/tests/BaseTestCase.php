<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\OnboardingModule\Repository\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repository\UserOnboardingGoalsRepository;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\ScenariosModule\Engine\Engine;
use Crm\ScenariosModule\Repository\ElementElementsRepository;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ElementStatsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggerElementsRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Crm\ScenariosModule\ScenariosModule;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeContentAccess;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Events\UserCreatedEvent;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\Tests\TestNotificationHandler;
use Kdyby\Translation\Translator;
use League\Event\Emitter;
use Tomaj\Hermes\Dispatcher;

abstract class BaseTestCase extends DatabaseTestCase
{
    /** @var ScenariosModule */
    protected $scenariosModule;

    /** @var Dispatcher */
    protected $dispatcher;

    /** @var Emitter */
    protected $emitter;

    /** @var Engine */
    protected $engine;

    /** @var TestNotificationHandler */
    protected $testNotificationHandler;

    protected function requiredRepositories(): array
    {
        return [
            UsersRepository::class,
            LoginAttemptsRepository::class,
            // To work with subscriptions, we need all these tables
            SubscriptionsRepository::class,
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
            SubscriptionTypeContentAccess::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
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
        $this->emitter = $this->inject(Emitter::class);

        $this->testNotificationHandler = new TestNotificationHandler();

        // Events are not automatically registered, we need to register them manually for tests
        $eventsStorage = $this->inject(EventsStorage::class);
        $eventsStorage->register('user_created', UserCreatedEvent::class, true);
        $eventsStorage->register('new_subscription', NewSubscriptionEvent::class, true);
        $eventsStorage->register('subscription_ends', SubscriptionEndsEvent::class, true);
        $eventsStorage->register('recurrent_payment_renewed', RecurrentPaymentRenewedEvent::class, true);
        $this->scenariosModule->registerHermesHandlers($this->dispatcher);

        // Email notification is going to be handled by test handler
        $this->emitter->addListener(NotificationEvent::class, $this->testNotificationHandler);

        $this->engine = $this->inject(Engine::class);
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
}
