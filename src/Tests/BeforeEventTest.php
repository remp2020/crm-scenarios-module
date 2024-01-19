<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Models\Event\BeforeEvent;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Events\BeforeEventGenerator;
use Crm\ScenariosModule\Events\EventGenerators\SubscriptionEndsEventGenerator;
use Crm\ScenariosModule\Repositories\GeneratedEventsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class BeforeEventTest extends BaseTestCase
{
    /** @var BeforeEventGenerator */
    protected $beforeEventGenerator;

    private SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager;

    public function setUp(): void
    {
        parent::setUp();

        /** @var TriggersRepository $triggerRepository */
        $triggerRepository = $this->getRepository(TriggersRepository::class);

        /** @var GeneratedEventsRepository $generatedEventsRepository */
        $generatedEventsRepository = $this->getRepository(GeneratedEventsRepository::class);

        /** @var Dispatcher $dispatcher */
        $dispatcher = $this->inject(Dispatcher::class);

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->subscriptionEndsSuppressionManager = $this->inject(SubscriptionEndsSuppressionManager::class);

        $subscriptionEndsEventGenerator = new SubscriptionEndsEventGenerator(
            $subscriptionsRepository,
            $this->subscriptionEndsSuppressionManager
        );

        $this->eventsStorage->registerEventGenerator('subscription_ends', $subscriptionEndsEventGenerator);

        $this->beforeEventGenerator = new BeforeEventGenerator(
            $this->eventsStorage,
            $triggerRepository,
            $generatedEventsRepository,
            $dispatcher
        );
    }

    protected function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            GeneratedEventsRepository::class,
        ]);
    }

    public function testSubscriptionEndsBeforeEventSingleEvent(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRow = $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'subscription_ends'],
                    'options' => self::obj(["minutes" => $minutes]),
                ])
            ]
        ]);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        $subscriptionRow = $this->prepareSubscription($userRow, $minutes);

        $result = $this->beforeEventGenerator->generate();

        $this->assertNotEmpty($result);

        /** @var BeforeEvent $beforeEvent */
        $beforeEvent = current($result)[0];
        $this->assertEquals($userRow->id, $beforeEvent->getUserId());
        $this->assertEquals($subscriptionRow->id, $beforeEvent->getParameters()['subscription_id']);

        /** @var JobsRepository $scenariosJobsRepository */
        $scenariosJobsRepository = $this->getRepository(JobsRepository::class);

        $this->assertEquals(1, $scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(1, $scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    public function testSubscriptionEndsBeforeEventSingleEventNotificationStopped(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRow = $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'subscription_ends'],
                    'options' => self::obj(["minutes" => $minutes]),
                ])
            ]
        ]);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        $subscriptionRow = $this->prepareSubscription($userRow, $minutes);
        $this->subscriptionEndsSuppressionManager->suppressNotifications($subscriptionRow);

        $result = $this->beforeEventGenerator->generate();

        $this->assertEmpty($result);

        /** @var JobsRepository $scenariosJobsRepository */
        $scenariosJobsRepository = $this->getRepository(JobsRepository::class);

        $this->assertEquals(0, $scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(0, $scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    public function testSubscriptionEndsBeforeEventMultipleTriggers(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'subscription_ends'],
                    'options' => self::obj(["minutes" => $minutes]),
                ]),
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger2',
                    'event' => ['code' => 'subscription_ends'],
                    'options' => self::obj(["minutes" => 2000]),
                ]),
            ]
        ]);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        $subscriptionRow = $this->prepareSubscription($userRow, $minutes);

        $result = $this->beforeEventGenerator->generate();

        $this->assertNotEmpty($result);

        /** @var BeforeEvent $beforeEvent */
        $beforeEvent = current($result)[0];
        $this->assertEquals($userRow->id, $beforeEvent->getUserId());
        $this->assertEquals($subscriptionRow->id, $beforeEvent->getParameters()['subscription_id']);

        /** @var JobsRepository $scenariosJobsRepository */
        $scenariosJobsRepository = $this->getRepository(JobsRepository::class);

        $this->assertEquals(1, $scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(1, $scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    public function testSubscriptionEndsBeforeEventMultipleSubscriptions(): void
    {
        $minutes = 1000;

        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRow = $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'subscription_ends'],
                    'options' => self::obj(["minutes" => $minutes]),
                ]),
            ]
        ]);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        $this->prepareSubscription($userRow, $minutes);
        $this->prepareSubscription($userRow, $minutes + 1000);
        $this->prepareSubscription($userRow, $minutes - 2);

        $stoppedNotificationsSubscription = $this->prepareSubscription($userRow, $minutes);
        $this->subscriptionEndsSuppressionManager->suppressNotifications($stoppedNotificationsSubscription);

        $result = $this->beforeEventGenerator->generate();

        $this->assertNotEmpty($result);

        /** @var BeforeEvent $beforeEvent */
        $beforeEvent = current($result)[0];
        $this->assertEquals($userRow->id, $beforeEvent->getUserId());

        /** @var JobsRepository $scenariosJobsRepository */
        $scenariosJobsRepository = $this->getRepository(JobsRepository::class);

        $this->assertEquals(2, $scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(2, $scenariosJobsRepository->getUnprocessedJobs()->count('*'));
    }

    private function prepareSubscription(ActiveRow $userRow, string $endTimeMinutesDiff): ActiveRow
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel('Test')
            ->setActive(1)
            ->setPrice(1)
            ->setLength(31)
            ->save();

        /** @var SubscriptionsRepository $subscriptionRepository */
        $subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);

        $now = new DateTime();
        $endTime = $now->modifyClone("+ {$endTimeMinutesDiff} minutes");

        return $subscriptionRepository->add(
            $subscriptionTypeRow,
            false,
            false,
            $userRow,
            SubscriptionsRepository::TYPE_REGULAR,
            $now,
            $endTime
        );
    }
}
