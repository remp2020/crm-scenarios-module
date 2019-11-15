<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\MailModule\Mailer\Repository\MailLayoutsRepository;
use Crm\MailModule\Mailer\Repository\MailTemplatesRepository;
use Crm\MailModule\Mailer\Repository\MailTypeCategoriesRepository;
use Crm\MailModule\Mailer\Repository\MailTypesRepository;
use Crm\OnboardingModule\Repository\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repository\UserOnboardingGoalsRepository;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Engine\Engine;
use Crm\ScenariosModule\Repository\ElementElementsRepository;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggerElementsRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\ScenariosModule;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Repository\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Events\UserCreatedEvent;
use Crm\UsersModule\Repository\LoginAttemptsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Kdyby\Translation\Translator;
use Tomaj\Hermes\Dispatcher;

abstract class BaseTestCase extends DatabaseTestCase
{
    /** @var ScenariosModule */
    protected $scenariosModule;

    /** @var Dispatcher */
    protected $dispatcher;

    /** @var Engine */
    protected $engine;

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
            // Tables to send emails
            MailLayoutsRepository::class,
            MailTypeCategoriesRepository::class,
            MailTypesRepository::class,
            MailTemplatesRepository::class,
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
            // Payments + recurrent payments
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            RecurrentPaymentsRepository::class,
            // User goals
            OnboardingGoalsRepository::class,
            UserOnboardingGoalsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class
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

        // Events are not automatically registered, we need to register them manually for tests
        $eventsStorage = $this->inject(EventsStorage::class);
        $eventsStorage->register('user_created', UserCreatedEvent::class, true);
        $eventsStorage->register('new_subscription', NewSubscriptionEvent::class, true);
        $eventsStorage->register('subscription_ends', SubscriptionEndsEvent::class, true);
        $eventsStorage->register('recurrent_payment_renewed', RecurrentPaymentRenewedEvent::class, true);
        $this->scenariosModule->registerHermesHandlers($this->dispatcher);
        $this->engine = $this->inject(Engine::class);
    }

    protected function elementId($uuid)
    {
        return $this->getRepository(ElementsRepository::class)->findBy('uuid', $uuid)->id;
    }

    protected function triggerId($uuid)
    {
        return $this->getRepository(TriggersRepository::class)->findBy('uuid', $uuid)->id;
    }

    public static function obj(array $array)
    {
        return json_decode(json_encode($array), false);
    }

    public function insertMailTemplate(string $template_code, string $subject = '', string $from = 'from@example.com')
    {
        /** @var MailTypeCategoriesRepository $mailTypeCategoryRepository */
        $mailTypeCategoryRepository = $this->getRepository(MailTypeCategoriesRepository::class);
        $mailTypeCategory = $mailTypeCategoryRepository->findBy('title', 'mail_cat1');
        if (!$mailTypeCategory) {
            $mailTypeCategory = $mailTypeCategoryRepository->add('mail_cat1', 1);
        }

        /** @var MailTypesRepository $mailTypeRepository */
        $mailTypeRepository = $this->getRepository(MailTypesRepository::class);
        $mailType = $mailTypeRepository->findBy('code', 'mail_type1');
        if (!$mailType) {
            $mailType = $mailTypeRepository->add('mail_type1', '', '', $mailTypeCategory->id);
        }

        /** @var MailLayoutsRepository $mlr */
        $mlr = $this->getRepository(MailLayoutsRepository::class);
        $layout = $mlr->findByName('mail_layout');
        if (!$layout) {
            $layout = $mlr->add('mail_layout', '', '');
        }

        /** @var MailTemplatesRepository $mtr */
        $mtr = $this->getRepository(MailTemplatesRepository::class);
        $mtr->add(
            $template_code,
            'Empty template',
            $layout->id,
            '',
            $from,
            $subject,
            '',
            '',
            $mailType->code
        );
    }
}
