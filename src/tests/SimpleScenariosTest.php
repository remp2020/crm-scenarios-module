<?php

namespace Crm\ScenariosModule\Tests;

use Crm\MailModule\Mailer\TestSender;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Engine\Engine;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Generator\SubscriptionsGenerator;
use Crm\SubscriptionsModule\Generator\SubscriptionsParams;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Mail\IMailer;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class SimpleScenariosTest extends BaseTestCase
{
    /** @var UserManager */
    private $userManager;

    /** @var TestSender */
    private $testEmailSender;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var SubscriptionsGenerator */
    private $subscriptionGenerator;

    /** @var SubscriptionsRepository */
    private $subscriptionRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->testEmailSender = $this->inject(IMailer::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionGenerator = $this->inject(SubscriptionsGenerator::class);
        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
    }

    public function testUserCreatedEmailScenario()
    {
        $this->insertTriggerToEmailScenario('user_created', 'empty_template_code');
        $this->insertMailTemplate('empty_template_code');

        $jr = $this->getRepository(JobsRepository::class);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $jr->getUnprocessedJobs()->fetchAll());

        $this->engine->run(true); // process trigger, finish its job and create email job

        $this->assertCount(1, $jr->getUnprocessedJobs()->fetchAll());

        $this->engine->run(true); // email job should be scheduled

        $this->assertCount(1, $jr->getScheduledJobs()->fetchAll());

        $this->dispatcher->handle(); // run email job in Hermes

        $this->assertCount(1, $jr->getFinishedJobs()->fetchAll());

        $this->engine->run(true); // job should be deleted

        $this->assertCount(0, $jr->getFinishedJobs()->fetchAll());
    }

    public function testUserCreatedWaitScenario()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_created'],
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
        $jr = $this->getRepository(JobsRepository::class);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $jr->getUnprocessedJobs()->fetchAll());

        $this->engine->run(true); // process trigger, finish its job and create wait job

        $this->assertCount(1, $jr->getUnprocessedJobs()->fetchAll());

        $this->engine->run(true); // wait job should be directly started

        $this->assertCount(1, $jr->getStartedJobs()->fetchAll());

        // 'execute_at' parameter is only supported in Redis driver for Hermes, dummy driver executes job right away
        $this->dispatcher->handle();

        $this->assertCount(1, $jr->getFinishedJobs()->fetchAll());

        $this->engine->run(true); // job should be deleted

        $this->assertCount(0, $jr->getFinishedJobs()->fetchAll());
    }

    public function testUserCreatedSegmentScenario()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_created'],
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
        $jr = $this->getRepository(JobsRepository::class);

        // Insert empty segment
        $segmentGroup = $this->getRepository(SegmentGroupsRepository::class)->add('test_group');
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

        $this->assertCount(1, $jr->getUnprocessedJobs()->fetchAll());

        $this->engine->run(true); // process trigger, finish its job and create segment job

        $this->assertCount(1, $jr->getUnprocessedJobs()->fetchAll());

        $this->engine->run(true); // schedule segment job

        $this->assertCount(1, $jr->getScheduledJobs()->fetchAll());

        $this->dispatcher->handle();

        $finishedJobs = $jr->getFinishedJobs()->fetchAll();
        $this->assertCount(1, $finishedJobs);
        $job = reset($finishedJobs);
        $jobResult = Json::decode($job->result);
        $this->assertFalse($jobResult->in); // User should not be in empty segment

        $this->engine->run(true); // all jobs should be deleted

        $this->assertCount(0, $jr->getAllJobs()->fetchAll());
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
                    'event' => ['code' => 'user_created'],
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
        $jr = $this->getRepository(JobsRepository::class);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // trigger: created -> finished, created segment job
        $this->engine->run(true); // created -> scheduled
        $this->dispatcher->handle(); // scheduled -> started -> failed (job failed, reason: non-existing segment)

        $this->assertCount(1, $jr->getFailedJobs()->fetchAll());
        $this->engine->run(true); // failed -> created

        // Check job was rescheduled and retry count was increased
        $unprocessedJobs = $jr->getUnprocessedJobs()->fetchAll();
        $this->assertCount(1, $unprocessedJobs);
        $job = reset($unprocessedJobs);
        $this->assertEquals(1, $job->retry_count);

        // Check after given number of retries, job is removed
        for ($i = 0; $i < Engine::MAX_RETRY_COUNT; $i++) {
            $this->engine->run(true); // created -> scheduled
            $this->dispatcher->handle(); // run (should fail)
            $this->engine->run(true); // failed -> created
        }
        $this->assertCount(0, $jr->getAllJobs()->fetchAll());
    }

    public function testNewSubscriptionEmailScenario()
    {
        $this->insertTriggerToEmailScenario('new_subscription', 'empty_template_code');
        $this->insertMailTemplate('empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(10)
            ->save();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime(),
            new DateTime()
        ), 1);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create email job
        $this->engine->run(true); // email job should be scheduled
        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(true); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->testEmailSender->getMailsSentTo('test@email.com'));
    }

    public function testSubscriptionEndsEmailScenario()
    {
        $this->insertTriggerToEmailScenario('subscription_ends', 'empty_template_code');
        $this->insertMailTemplate('empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create actual subscription
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(10)
            ->save();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime('now - 15 minutes'),
            new DateTime('now + 5 minutes')
        ), 1);

        // Expire subscription (this triggers scenario)
        $subscription = $this->subscriptionRepository->actualUserSubscription($user->id);
        $this->subscriptionRepository->setExpired($subscription, new DateTime('now - 5 minutes'));

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create email job
        $this->engine->run(true); // email job should be scheduled
        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(true); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->testEmailSender->getMailsSentTo('test@email.com'));
    }

    public function testRecurrentPayentRenewedEmailScenario()
    {
        $this->insertTriggerToEmailScenario('recurrent_payment_renewed', 'empty_template_code');
        $this->insertMailTemplate('empty_template_code');

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Create actual subscription
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(10)
            ->save();

        // Create payment
        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGateway = $pgr->add('test', 'test', 10, true, true);

        $pr = $this->inject(PaymentsRepository::class);
        $payment = $pr->add($subscriptionType, $paymentGateway, $user, new PaymentItemContainer(), null, 1);
        $payment2 = $pr->add($subscriptionType, $paymentGateway, $user, new PaymentItemContainer(), null, 1);

        $rpp = $this->inject(RecurrentPaymentsRepository::class);
        $recurrentPayment = $rpp->add('XXX', $payment, new DateTime('now + 5 minutes'), 1, 1);

        // Recharge recurrent - this should trigger scenario
        $rpp->setCharged($recurrentPayment, $payment2, 'OK', 'OK');

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create email job
        $this->engine->run(true); // email job should be scheduled
        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(true); // job should be deleted

        // Check email was sent
        $this->assertCount(1, $this->testEmailSender->getMailsSentTo('test@email.com'));
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