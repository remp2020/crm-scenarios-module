<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios\EventGenerators;

use Crm\ApplicationModule\Models\NowTrait;
use Crm\ScenariosModule\Events\BeforeEventGenerator;
use Crm\ScenariosModule\Events\EventGenerators\AfterLastSubscriptionEndedEventGenerator;
use Crm\ScenariosModule\Repositories\GeneratedEventsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\ScenariosModule\Tests\BaseTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Scenarios\AfterLastSubscriptionEndedEvent;
use Crm\UsersModule\Repositories\UsersRepository;
use DateInterval;
use DateTime;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;

class AfterLastSubscriptionEndedEventGeneratorTest extends BaseTestCase
{
    use NowTrait;

    private JobsRepository $jobsRepository;

    private BeforeEventGenerator $beforeEventGenerator;

    public function setUp(): void
    {
        parent::setUp();

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->jobsRepository = $jobsRepository;

        $this->eventsStorage->register(
            AfterLastSubscriptionEndedEventGenerator::BEFORE_EVENT_CODE,
            AfterLastSubscriptionEndedEvent::class,
            true,
        );
        $this->eventsStorage->registerEventGenerator(
            AfterLastSubscriptionEndedEventGenerator::BEFORE_EVENT_CODE,
            $this->inject(AfterLastSubscriptionEndedEventGenerator::class),
        );
        $this->beforeEventGenerator = $this->inject(BeforeEventGenerator::class);
    }

    public function tearDown(): void
    {
        // reset global and event NOW
        $this->setNow(null);
        /** @var AfterLastSubscriptionEndedEventGenerator $afterLastSubscriptionEndedEventGenerator */
        $afterLastSubscriptionEndedEventGenerator = $this->inject(AfterLastSubscriptionEndedEventGenerator::class);
        $afterLastSubscriptionEndedEventGenerator->setNow(null);

        parent::tearDown();
    }

    protected function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            GeneratedEventsRepository::class,
        ]);
    }

    public function testNoSubscription()
    {
        $result = $this->beforeEventGenerator->generate();
        $this->assertCount(0, $result);
    }

    public static function dataProviderSubscriptions(): array
    {
        $now = new DateTime('2025-08-21 13:00:42');
        $conditionEnded60daysAgoInMinutes = 60*24*93;
        // date 2025-05-20 13:00:42
        $conditionDateTimeThreshold = (clone $now)->sub(new DateInterval("PT{$conditionEnded60daysAgoInMinutes}M"));

        return [
            'endedThresholdDaysAgo' => [
                'now' => $now,
                'lastSubscriptionEndedMinutesAgoCondition' => $conditionEnded60daysAgoInMinutes,
                'subscriptionEndTime' => $conditionDateTimeThreshold, // same as scenario condition - upper threshold
                'expectedResult' => true, // should be triggered
            ],
            'ended15MinutesBeforeThreshold' => [
                'now' => $now,
                'lastSubscriptionEndedMinutesAgoCondition' => $conditionEnded60daysAgoInMinutes,
                'subscriptionEndTime' => (clone $conditionDateTimeThreshold)->modify('-15 minutes'),
                'expectedResult' => true, // should be triggered
            ],
            'ended30MinutesBeforeThreshold' => [
                'now' => $now,
                'lastSubscriptionEndedMinutesAgoCondition' => $conditionEnded60daysAgoInMinutes,
                'subscriptionEndTime' => (clone $conditionDateTimeThreshold)->modify('-30 minutes'), // lower threshold
                'expectedResult' => true, // should be triggered
            ],
            'FAIL_ended31MinutesBeforeThreshold' => [
                'now' => $now,
                'lastSubscriptionEndedMinutesAgoCondition' => $conditionEnded60daysAgoInMinutes,
                'subscriptionEndTime' => (clone $conditionDateTimeThreshold)->modify('-31 minutes'), // outside of period
                'expectedResult' => false, // we are too late; would be processed in the previous minute
            ],
            'FAIL_ended1MinuteAfterThreshold' => [
                'now' => $now,
                'lastSubscriptionEndedMinutesAgoCondition' => $conditionEnded60daysAgoInMinutes,
                'subscriptionEndTime' => (clone $conditionDateTimeThreshold)->modify('+1 minutes'), // outside of period
                'expectedResult' => false, // we are not yet within interval; will be processed in a minute
            ],
            'FAIL_currentlyActive' => [
                'now' => $now,
                'lastSubscriptionEndedMinutesAgoCondition' => $conditionEnded60daysAgoInMinutes,
                'subscriptionEndTime' => (clone $now)->modify('+14 days') , // still active
                'expectedResult' => false, // still running
            ],
        ];
    }

    #[DataProvider('dataProviderSubscriptions')]
    public function testSubscriptions(
        DateTime $now,
        int $lastSubscriptionEndedMinutesAgoCondition,
        DateTime $subscriptionEndTime,
        bool $expectedResult,
    ) {
        $this->setNowForAll($now);
        $this->createScenario($lastSubscriptionEndedMinutesAgoCondition);

        $to = $subscriptionEndTime;
        $from = (clone $subscriptionEndTime)->sub(new \DateInterval('P30D'));
        $subscription = $this->addSubscription(startTime: $from, endTime: $to);

        $result = $this->beforeEventGenerator->generate();
        $this->assertCount((int) $expectedResult, $result);

        if ($expectedResult) {
            // check parameters of job
            $allJobs = $this->jobsRepository->getAllJobs();
            $this->assertCount(1, $allJobs);

            $job = $allJobs->fetch();
            $params = Json::decode($job->parameters);
            $this->assertEquals($subscription->user_id, $params->user_id);
            $this->assertEquals($subscription->id, $params->subscription_id);
            $this->assertEquals($subscription->subscription_type_id, $params->subscription_type_id);
        }
    }

    /**
     * Use same provider but add following subscription. This should always fail.
     */
    #[DataProvider('dataProviderSubscriptions')]
    public function testMatchingSubscriptionWithFollowingSubscriptionAlwaysFails(
        DateTime $now,
        int $lastSubscriptionEndedMinutesAgoCondition,
        DateTime $subscriptionEndTime,
        bool $expectedResult,
    ) {
        $this->setNowForAll($now);
        $this->createScenario($lastSubscriptionEndedMinutesAgoCondition);

        $to = $subscriptionEndTime;
        $from = (clone $subscriptionEndTime)->sub(new \DateInterval('P30D'));
        $this->addSubscription(startTime: $from, endTime: $to);

        // but we add following subscription
        $nextFrom = clone $to;
        $nextTo = (clone $nextFrom)->add(new \DateInterval('P30D'));
        $this->addSubscription(startTime: $nextFrom, endTime: $nextTo);

        $result = $this->beforeEventGenerator->generate();
        // always 0; there is following subscription, won't be processed
        // $expectedResult is ignored
        $this->assertCount(0, $result);
    }

    /**
     * Almost same test as testMatchingSubscriptionWithFollowingSubscription
     * but newer subscription is not connected to old "matching" subscription
     * and is currently active. This should always fail.
     */
    #[DataProvider('dataProviderSubscriptions')]
    public function testMatchingSubscriptionWithCurrentSubscriptionAlwaysFails(
        DateTime $now,
        int $lastSubscriptionEndedMinutesAgoCondition,
        DateTime $subscriptionEndTime,
        bool $expectedResult,
    ) {
        $this->setNowForAll($now);
        $this->createScenario($lastSubscriptionEndedMinutesAgoCondition);

        $to = $subscriptionEndTime;
        $from = (clone $subscriptionEndTime)->sub(new \DateInterval('P30D'));
        $this->addSubscription(startTime: $from, endTime: $to);

        // but we add subscription which is currently active
        $now = $this->getNow();
        $nextFrom = (clone $now)->modify('-2 days');
        $nextTo = (clone $nextFrom)->add(new \DateInterval('P30D'));
        $this->addSubscription(startTime: $nextFrom, endTime: $nextTo);

        $result = $this->beforeEventGenerator->generate();
        // always 0; there is following subscription, won't be processed
        // $expectedResult is ignored
        $this->assertCount(0, $result);
    }

    /* **********************************************************
     * Helper methods
     * *********************************************************/

    private function setNowForAll(DateTime $now)
    {
        $this->setNow($now);
        /** @var AfterLastSubscriptionEndedEventGenerator $afterLastSubscriptionEndedEventGenerator */
        $afterLastSubscriptionEndedEventGenerator = $this->inject(AfterLastSubscriptionEndedEventGenerator::class);
        $afterLastSubscriptionEndedEventGenerator->setNow($this->getNow());
    }

    private function createScenario(int $conditionMinutes): ActiveRow
    {
        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenario = $scenariosRepository->createOrUpdate(['name' => 'test1',
            'enabled' => true,
            'triggers' => [
                (object) [
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger1',
                    'event' => (object) ['code' => AfterLastSubscriptionEndedEventGenerator::BEFORE_EVENT_CODE],
                    'options' => (object) ['minutes' => $conditionMinutes],
                ],
            ],
        ]);

        return $scenario;
    }

    private function addSubscription(
        \DateTime $startTime = null,
        \DateTime $endTime = null,
    ): ActiveRow {
        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);

        return $subscriptionsRepository->add(
            subscriptionType: $this->getSubscriptionType(),
            isRecurrent: false,
            isPaid: true,
            user: $this->getUser(),
            startTime: $startTime,
            endTime: $endTime,
        );
    }

    private function getUser()
    {
        $email = 'user@example.com';

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);

        $user = $usersRepository->getByEmail($email);
        if ($user) {
            return $user;
        }
        return $usersRepository->add($email, 'pwdpwd');
    }

    private function getSubscriptionType()
    {
        $subscriptionTypeCode = 'test_subscription';

        /** @var SubscriptionTypesRepository $subscriptionTypesRepository */
        $subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);

        $subscriptionType = $subscriptionTypesRepository->findByCode($subscriptionTypeCode);
        if ($subscriptionType) {
            return $subscriptionType;
        }

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        return $subscriptionTypeBuilder
            ->createNew()
            ->setName($subscriptionTypeCode)
            ->setUserLabel($subscriptionTypeCode)
            ->setActive(true)
            ->setPrice(1)
            ->setLength(365)
            ->save();
    }
}
