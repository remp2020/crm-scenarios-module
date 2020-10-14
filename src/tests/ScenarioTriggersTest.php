<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Generator\SubscriptionsGenerator;
use Crm\SubscriptionsModule\Generator\SubscriptionsParams;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Utils\DateTime;
use Tomaj\Hermes\Emitter;

class ScenarioTriggersTest extends BaseTestCase
{
    /** @var UserManager */
    private $userManager;

    /** @var Emitter */
    private $hermesEmitter;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var SubscriptionsGenerator */
    private $subscriptionGenerator;

    /** @var SubscriptionsRepository */
    private $subscriptionRepository;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->userManager = $this->inject(UserManager::class);
        $this->hermesEmitter = $this->inject(Emitter::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionGenerator = $this->inject(SubscriptionsGenerator::class);
        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
    }

    public function testTriggerUserCreatedScenario()
    {
        $this->addTestScenario('user_created');

        // Add user, which triggers scenario
        $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(true); // process trigger

        $this->userManager->addNewUser('user2@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(true); // process trigger

        $this->userManager->addNewUser('user3@email.com', false, 'unknown', null, false);
        $this->dispatcher->handle();
        $this->engine->run(true); // process trigger

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Check stats
        // Triggers are only CREATED and then FINISHED
        $tsr = $this->getRepository(TriggerStatsRepository::class);
        $triggerStats = $tsr->countsFor($this->triggerId('trigger1'));
        $this->assertEquals(3, $triggerStats[JobsRepository::STATE_CREATED]);
        $this->assertEquals(3, $triggerStats[JobsRepository::STATE_FINISHED]);
    }

    public function testTriggerNewSubscription()
    {
        $this->addTestScenario('new_subscription');

        $user1 = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(62)
            ->save();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user1,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime('now - 1 month'),
            new DateTime('now + 1 month'),
            false
        ), 1);

        $subscription = $this->subscriptionRepository->actualUserSubscription($user1->id);

        $this->dispatcher->handle();

        /** @var JobsRepository $jobsRepository */
        $jobsRepository = $this->getRepository(JobsRepository::class);

        // There should be 1 trigger now
        $this->assertEquals(1, count($jobsRepository->getUnprocessedJobs()));

        $this->hermesEmitter->emit(new HermesMessage('new-subscription', [
            'subscription_id' => $subscription->id,
            'send_email' => true,
        ]));
        $this->dispatcher->handle();

        // There should be 2 triggers now
        $this->assertEquals(2, count($jobsRepository->getUnprocessedJobs()));

        // If send_email is false, we shouldn't trigger new-subscriptions scenario
        $this->hermesEmitter->emit(new HermesMessage('new-subscription', [
            'subscription_id' => $subscription->id,
            'send_email' => false,
        ]));
        $this->dispatcher->handle();

        // There should be 2 triggers now
        $this->assertEquals(2, count($jobsRepository->getUnprocessedJobs()));
    }

    public function testTriggerNewPayment()
    {
        $this->addTestScenario('new_payment');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', 'bank_transfer');

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Trigger the scenario
        $this->paymentsRepository->add(
            null,
            $paymentGatewayRow,
            $user1,
            new PaymentItemContainer(),
            null,
            1
        );

        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    public function testTriggerPaymentChangeStatus()
    {
        $this->addTestScenario('payment_change_status');

        $user1 = $this->userManager->addNewUser('user1@email.com', false, 'unknown', null, false);

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', 'bank_transfer');

        $paymentRow = $this->paymentsRepository->add(
            null,
            $paymentGatewayRow,
            $user1,
            new PaymentItemContainer(),
            null,
            1
        );
        $this->dispatcher->handle();

        $jobsRepository = $this->getRepository(JobsRepository::class);
        $this->assertCount(0, $jobsRepository->getUnprocessedJobs()->fetchAll());

        // Trigger the scenario
        $this->paymentsRepository->updateStatus($paymentRow, PaymentsRepository::STATUS_PAID, true);
        $this->dispatcher->handle();
        $this->assertCount(1, $jobsRepository->getUnprocessedJobs()->fetchAll());
    }

    private function addTestScenario($triggerCode)
    {
        $scenarioRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => $triggerCode],
                ])
            ]
        ]);
    }
}
