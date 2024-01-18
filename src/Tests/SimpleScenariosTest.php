<?php

namespace Crm\ScenariosModule\Tests;

use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Generator\SubscriptionsGenerator;
use Crm\SubscriptionsModule\Models\Generator\SubscriptionsParams;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationContext;
use Crm\UsersModule\Events\PreNotificationEvent;
use Crm\UsersModule\Models\Auth\UserManager;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class SimpleScenariosTest extends BaseTestCase
{
    private UserManager $userManager;

    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    private SubscriptionsGenerator $subscriptionGenerator;

    private SubscriptionsRepository $subscriptionRepository;

    private PaymentsRepository $paymentsRepository;

    private RecurrentPaymentsRepository $recurrentPaymentsRepository;

    private JobsRepository $jobsRepository;

    private SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionGenerator = $this->inject(SubscriptionsGenerator::class);
        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->subscriptionEndsSuppressionManager = $this->inject(SubscriptionEndsSuppressionManager::class);
    }

    public function testUserRegisteredEmailScenario()
    {
        $this->insertTriggerToEmailScenario('user_registered', 'empty_template_code');

        // Handler to check if context is added to PreNotificationEvent
        $preNotificationEventHandler = new class extends AbstractListener {
            public NotificationContext $notificationContext;

            /**
             * @param PreNotificationEvent $event
             */
            public function handle(EventInterface $event)
            {
                $this->notificationContext = $event->getNotificationContext();
            }
        };
        $this->lazyEventEmitter->addListener(PreNotificationEvent::class, $preNotificationEventHandler);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(2); // trigger created -> finished -> element created

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(1); // element created -> scheduled

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        $this->dispatcher->handle(); // run email job in Hermes

        $this->assertCount(1, $this->jobsRepository->getFinishedJobs()->fetchAll());

        // Check hermes message type is passed down in context
        $jobContext = Json::decode($this->jobsRepository->getFinishedJobs()->fetch()->context, Json::FORCE_ARRAY);
        $this->assertEquals('user-registered', $jobContext[JobsRepository::CONTEXT_HERMES_MESSAGE_TYPE]);

        $this->engine->run(1); // job should be deleted

        $this->assertCount(0, $this->jobsRepository->getFinishedJobs()->fetchAll());

        // Check notification context contains hermes trigger
        $this->assertEquals('user-registered', $preNotificationEventHandler->notificationContext->getContextValue(NotificationContext::HERMES_MESSAGE_TYPE));

        // cleanup
        $this->lazyEventEmitter->removeAllListeners(PreNotificationEvent::class);
    }

    public function testUserRegisteredWaitScenario()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10]
                ])
            ]
        ]);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(2); // process trigger, finish its job and create wait job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(2); // wait job should be directly started

        $this->assertCount(1, $this->jobsRepository->getStartedJobs()->fetchAll());

        // 'execute_at' parameter is only supported in Redis driver for Hermes, dummy driver executes job right away
        $this->dispatcher->handle();

        $this->assertCount(1, $this->jobsRepository->getFinishedJobs()->fetchAll());

        $this->engine->run(1); // job should be deleted

        $this->assertCount(0, $this->jobsRepository->getFinishedJobs()->fetchAll());
    }

    public function testUserRegisteredSegmentScenario()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_segment']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_segment',
                    'type' => ElementsRepository::ELEMENT_TYPE_SEGMENT,
                    'segment' => ['code' => 'empty_segment']
                ])
            ]
        ]);
        // Insert empty segment
        $segmentGroup = $this->getRepository(SegmentGroupsRepository::class)->add('Test group', 'test_group');
        $this->getRepository(SegmentsRepository::class)->add(
            'Empty segment',
            1,
            'empty_segment',
            'users',
            'users.id',
            'SELECT %fields% FROM %table% WHERE %where% AND 1=2',
            $segmentGroup
        );

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(2); // process trigger, finish its job and create segment job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(1); // schedule segment job

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        $this->dispatcher->handle();

        $finishedJobs = $this->jobsRepository->getFinishedJobs()->fetchAll();
        $this->assertCount(1, $finishedJobs);
        $job = reset($finishedJobs);
        $jobResult = Json::decode($job->result);
        $this->assertFalse($jobResult->in); // User should not be in empty segment

        $this->engine->run(1); // all jobs should be deleted

        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());
    }

    public function testFailingSegmentScenario()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_segment']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_segment',
                    'type' => ElementsRepository::ELEMENT_TYPE_SEGMENT,
                    'segment' => ['code' => 'non_existing_segment']
                ])
            ]
        ]);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // trigger: created -> finished, created segment job
        $this->dispatcher->handle(); // scheduled -> started -> failed (job failed, reason: non-existing segment)

        $this->assertCount(1, $this->jobsRepository->getFailedJobs()->fetchAll());
        $this->engine->run(1); // failed job is deleted (since missing-segment-error is not a job that can succeed later)

        // Check job was deleted
        $allJobs = $this->jobsRepository->getAllJobs()->fetchAll();
        $this->assertCount(0, $allJobs);
    }

    public function testNewSubscriptionEmailScenario()
    {
        $emailCode = 'empty_template_code';
        $this->insertTriggerToEmailScenario('new_subscription', $emailCode);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionTypeCode = 'test_subscription';
        $subscriptionType = $this->createSubscriptionType($subscriptionTypeCode);

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime(),
            new DateTime(),
            false
        ), 1);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // process trigger, finish its job, create and schedule email job

        // check email job has 'subscription_id' parameter
        $emailJob = $this->jobsRepository->getScheduledJobs()->fetch();
        $emailJobParameters = Json::decode($emailJob->parameters);
        $this->assertNotEmpty($emailJobParameters->subscription_id);

        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));

        // Check email's access to parameters
        $emailParams = $this->notificationEmailParams('test@email.com', $emailCode);
        $this->assertEquals($user->email, $emailParams['email']);
        $this->assertEquals($subscriptionTypeCode, $emailParams['subscription_type']['code']);
    }

    public function testSubscriptionStartsEmailScenario()
    {
        $this->insertTriggerToEmailScenario('subscription_starts', 'empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create actual subscription
        $subscriptionType = $this->createSubscriptionType();

        $subscriptions = $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime('now + 5 minutes'),
            new DateTime('now + 30 minutes'),
            false
        ), 1);

        // check scheduled jobs - should be still empty (subscription didn't start yet)
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);
        $job = $this->jobsRepository->getScheduledJobs()->fetch();
        $this->assertNull($job);

        // Move start of subscription to past (this triggers update of internal subscription status and scenario)
        $subscription = $subscriptions[0];
        $this->subscriptionRepository->update($subscription, ['start_time' => new DateTime('now - 5 minutes')]);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);

        // check email job has 'subscription_id' parameter
        $emailJob = $this->jobsRepository->getScheduledJobs()->fetch();
        $emailJobParameters = Json::decode($emailJob->parameters);
        $this->assertNotEmpty($emailJobParameters->subscription_id);

        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));
    }

    public function testSubscriptionEndsEmailScenario()
    {
        $this->insertTriggerToEmailScenario('subscription_ends', 'empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create actual subscription
        $subscriptionType = $this->createSubscriptionType();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime('now - 15 minutes'),
            new DateTime('now + 5 minutes'),
            false
        ), 1);

        // check scheduled jobs - should be still empty (subscription didn't end yet)
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);
        $job = $this->jobsRepository->getScheduledJobs()->fetch();
        $this->assertNull($job);

        // Expire subscription (this triggers scenario)
        $subscription = $this->subscriptionRepository->actualUserSubscription($user->id);
        $this->subscriptionRepository->update($subscription, ['end_time' => new DateTime('now - 5 minutes')]);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);

        // check email job has 'subscription_id' parameter
        $emailJob = $this->jobsRepository->getScheduledJobs()->fetch();
        $emailJobParameters = Json::decode($emailJob->parameters);
        $this->assertNotEmpty($emailJobParameters->subscription_id);

        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));
    }

    public function testSubscriptionEndsNotificationStoppedEmailScenario()
    {
        $this->insertTriggerToEmailScenario('subscription_ends', 'empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create actual subscription
        $subscriptionType = $this->createSubscriptionType();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime('now - 15 minutes'),
            new DateTime('now + 5 minutes'),
            false
        ), 1);

        // check scheduled jobs - should be still empty (subscription didn't end yet)
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);
        $job = $this->jobsRepository->getScheduledJobs()->fetch();
        $this->assertNull($job);

        // Expire subscription (this triggers scenario)
        $subscription = $this->subscriptionRepository->actualUserSubscription($user->id);
        $this->subscriptionRepository->update($subscription, ['end_time' => new DateTime('now - 5 minutes')]);
        $this->subscriptionEndsSuppressionManager->suppressNotifications($subscription);

        // check scheduled jobs - should be still empty (subscription with no notifications meta)
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);
        $job = $this->jobsRepository->getScheduledJobs()->fetch();
        $this->assertNull($job);
    }

    public function testRecurrentPayentRenewedEmailScenario()
    {
        $this->insertTriggerToEmailScenario('recurrent_payment_renewed', 'empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);
        $subscriptionType = $this->createSubscriptionType();

        // Create payment
        $paymentGateway = $this->createTestPaymentGateway();
        $payment = $this->paymentsRepository->add($subscriptionType, $paymentGateway, $user, new PaymentItemContainer(), null, 1);
        $payment2 = $this->paymentsRepository->add($subscriptionType, $paymentGateway, $user, new PaymentItemContainer(), null, 1);
        $recurrentPayment = $this->recurrentPaymentsRepository->add('XXX', $payment, new DateTime('now + 5 minutes'), 1, 1);

        // Recharge recurrent - this should trigger scenario
        $this->recurrentPaymentsRepository->setCharged($recurrentPayment, $payment2, 'OK', 'OK');

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);

        // check email job has 'subscription_id' parameter
        $emailJob = $this->jobsRepository->getScheduledJobs()->fetch();

        /** @var ?\stdClass $emailJobParameters */
        $emailJobParameters = Json::decode($emailJob->parameters);
        $this->assertNotEmpty($emailJobParameters->payment_id);
        $this->assertNotEmpty($emailJobParameters->recurrent_payment_id);

        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));
    }

    public function testNewPaymentEmailScenario()
    {
        $emailCode = 'empty_template_code';
        $this->insertTriggerToEmailScenario('new_payment', $emailCode);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create payment
        $subscriptionType = $this->createSubscriptionType();
        $this->paymentsRepository->add($subscriptionType, $this->createTestPaymentGateway(), $user, new PaymentItemContainer(), null, 1);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);
        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));

        // Check email's access to parameters
        $emailParams = $this->notificationEmailParams('test@email.com', $emailCode);
        $this->assertEquals($user->email, $emailParams['email']);
        $this->assertEquals($subscriptionType->code, $emailParams['subscription_type']['code']);
    }

    public function testRecurrentPaymentStateChangeEmailScenario()
    {
        $emailCode = 'empty_template_code';
        $this->insertTriggerToEmailScenario('recurrent_payment_state_changed', $emailCode);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create payment
        $subscriptionType = $this->createSubscriptionType();
        $paymentGateway = $this->createTestPaymentGateway();

        $payment = $this->paymentsRepository->add($subscriptionType, $paymentGateway, $user, new PaymentItemContainer(), null, 1);
        $recurrentPayment = $this->recurrentPaymentsRepository->add('XXX', $payment, new DateTime('now + 5 minutes'), 1, 1);

        // Change status of recurrent payment - this should trigger scenario
        $this->recurrentPaymentsRepository->update($recurrentPayment, ['state' => RecurrentPaymentsRepository::STATE_USER_STOP]);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3);
        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1);

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));

        // Check email's access to parameters
        $emailParams = $this->notificationEmailParams('test@email.com', $emailCode);
        $this->assertEquals($user->email, $emailParams['email']);
        $this->assertEquals($subscriptionType->code, $emailParams['subscription_type']['code']);
    }

    private function createSubscriptionType(string $subscriptionTypeCode = 'test_subscription')
    {
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('Test subscription')
            ->setCode($subscriptionTypeCode)
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(10)
            ->save();
        return $subscriptionType;
    }

    private function createTestPaymentGateway()
    {
        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        return $pgr->add('test', 'test', 10, true, true);
    }

    private function insertTriggerToEmailScenario(string $trigger, string $emailCode)
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => $trigger],
                    'elements' => ['element_email']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_email',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => $emailCode]
                ])
            ]
        ]);
    }
}
