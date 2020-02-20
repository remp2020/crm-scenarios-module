<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Generator\SubscriptionsGenerator;
use Crm\SubscriptionsModule\Generator\SubscriptionsParams;
use Crm\SubscriptionsModule\Repository\ContentAccessRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\SubscriptionsModule;
use Crm\UsersModule\Auth\Permissions;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Crm\UsersModule\UsersModule;
use Kdyby\Translation\Translator;
use Nette\Security\User;
use Nette\Utils\DateTime;

class ConditionElementTest extends BaseTestCase
{
    const EMAIL_TEMPLATE_SUCCESS = 'success_email';
    const EMAIL_TEMPLATE_FAIL = 'fail_email';

    const SUBSCRIPTION_TYPE_STANDARD = 'standard_subscription';
    const SUBSCRIPTION_TYPE_CLUB = 'club_subscription';

    /** @var UserManager */
    private $userManager;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var SubscriptionsGenerator */
    private $subscriptionGenerator;

    /** @var SubscriptionsRepository */
    private $subscriptionRepository;

    /** @var JobsRepository */
    private $jobsRepository;

    /** @var ScenariosCriteriaStorage */
    private $scenariosCriteriaStorage;

    /** @var ContentAccessRepository */
    private $contentAccessRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userManager = $this->inject(UserManager::class);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $this->subscriptionGenerator = $this->inject(SubscriptionsGenerator::class);
        $this->subscriptionRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->contentAccessRepository = $this->getRepository(ContentAccessRepository::class);

        // Register modules' scenarios criteria storage
        $this->scenariosCriteriaStorage = $this->inject(ScenariosCriteriaStorage::class);

        $subscriptionsModule = new SubscriptionsModule($this->container, $this->inject(Translator::class), $this->subscriptionRepository);
        $subscriptionsModule->registerScenariosCriteria($this->scenariosCriteriaStorage);

        $usersModule = new UsersModule(
            $this->container,
            $this->inject(Translator::class),
            $this->createMock(User::class),
            $this->createMock(Permissions::class),
            $this->getRepository(UsersRepository::class)
        );
        $usersModule->registerScenariosCriteria($this->scenariosCriteriaStorage);
    }

    /**
     * Test scenario with TRIGGER -> CONDITION -> MAIL (positive) flow
     */
    public function testSubscriptionConditionPositiveFlow()
    {
        $this->insertScenario1(self::SUBSCRIPTION_TYPE_STANDARD);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName(self::SUBSCRIPTION_TYPE_STANDARD)
            ->setCode(self::SUBSCRIPTION_TYPE_STANDARD)
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

        // SIMULATE RUN
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create condition job
        $this->engine->run(true); // job(cond): created -> scheduled
        $this->dispatcher->handle(); // job(cond): scheduled -> started -> finished
        $this->engine->run(true); // job(cond): deleted, job(email): created
        $this->engine->run(true); // job(email): created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished
        $this->engine->run(true); // job(email): deleted

        // Check email was sent
        $mails = $this->mailsSentTo('test@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals(self::EMAIL_TEMPLATE_SUCCESS, $mails[0]);
    }

    /**
     * Test scenario with TRIGGER -> CONDITION -> MAIL (negative) flow
     */
    public function testSubscriptionConditionNegativeFlow()
    {
        $this->insertScenario1(self::SUBSCRIPTION_TYPE_CLUB);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName(self::SUBSCRIPTION_TYPE_STANDARD)
            ->setCode(self::SUBSCRIPTION_TYPE_STANDARD)
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

        // SIMULATE RUN
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create condition job
        $this->engine->run(true); // job(cond): created -> scheduled
        $this->dispatcher->handle(); // job(cond): scheduled -> started -> finished
        $this->engine->run(true); // job(cond): deleted, job(email): created
        $this->engine->run(true); // job(email): created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished
        $this->engine->run(true); // job(email): deleted

        // Check email was sent
        $mails = $this->mailsSentTo('test@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals(self::EMAIL_TEMPLATE_FAIL, $mails[0]);
    }

    private function insertScenario1($checkForSubscriptionType)
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'new_subscription'],
                    'elements' => ['element_condition']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_condition',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'descendants' => [
                            ['uuid' => 'element_email_pos', 'direction' => 'positive'],
                            ['uuid' => 'element_email_neg', 'direction' => 'negative']
                        ],
                        'conditions' => [
                            'event' => 'subscription',
                            'version' => 1,
                            'nodes' => [
                                [
                                    'id' => 1,
                                    'key' => 'type',
                                    'values' => [
                                        'selection'=> ['free'],
                                        'operator' => 'or'
                                    ]
                                ],
                                [
                                    'id' => 2,
                                    'key' => 'subscription_type',
                                    'values' => [
                                        'selection' => [$checkForSubscriptionType],
                                        'operator' => 'or'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_pos',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_SUCCESS]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_neg',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_FAIL]
                ])
            ]
        ]);
    }

    /**
     * Test scenario with TRIGGER -> CONDITION -> MAIL (positive) flow
     */
    public function testContentAccessConditionPositiveFlow()
    {
        $this->contentAccessRepository->add('web', 'Web access');
        $this->contentAccessRepository->add('plus', 'Plus access');

        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'new_subscription'],
                    'elements' => ['element_condition']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_condition',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'descendants' => [
                            ['uuid' => 'element_email_pos', 'direction' => 'positive'],
                            ['uuid' => 'element_email_neg', 'direction' => 'negative']
                        ],
                        'conditions' => [
                            'event' => 'subscription',
                            'version' => 1,
                            'nodes' => [
                                [
                                    'id' => 1,
                                    'key' => 'content_access',
                                    'values' => [
                                        'selection'=> ['plus'],
                                        'operator' => 'or'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_pos',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_SUCCESS]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_neg',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_FAIL]
                ])
            ]
        ]);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName(self::SUBSCRIPTION_TYPE_STANDARD)
            ->setCode(self::SUBSCRIPTION_TYPE_STANDARD)
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(1)
            ->setLength(10)
            ->setContentAccessOption('web', 'plus')
            ->save();

        $this->subscriptionGenerator->generate(new SubscriptionsParams(
            $subscriptionType,
            $user,
            SubscriptionsRepository::TYPE_FREE,
            new DateTime(),
            new DateTime()
        ), 1);

        // SIMULATE RUN
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create condition job
        $this->engine->run(true); // job(cond): created -> scheduled
        $this->dispatcher->handle(); // job(cond): scheduled -> started -> finished
        $this->engine->run(true); // job(cond): deleted, job(email): created
        $this->engine->run(true); // job(email): created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished
        $this->engine->run(true); // job(email): deleted

        // Check email was sent
        $mails = $this->mailsSentTo('test@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals(self::EMAIL_TEMPLATE_SUCCESS, $mails[0]);
    }

    /**
     * Test scenario with TRIGGER -> CONDITION -> MAIL (positive) flow
     */
    public function testIsRecurrentConditionPositiveFlow()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'new_subscription'],
                    'elements' => ['element_condition']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_condition',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'descendants' => [
                            ['uuid' => 'element_email_pos', 'direction' => 'positive'],
                            ['uuid' => 'element_email_neg', 'direction' => 'negative']
                        ],
                        'conditions' => [
                            'event' => 'subscription',
                            'version' => 1,
                            'nodes' => [
                                [
                                    'id' => 1,
                                    'key' => 'is_recurrent',
                                    'values' => [
                                        'selection' => false
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_pos',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_SUCCESS]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_neg',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_FAIL]
                ])
            ]
        ]);

        // Create user
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        // Add new subscription, which triggers scenario
        $subscriptionType = $this->subscriptionTypeBuilder
            ->createNew()
            ->setName(self::SUBSCRIPTION_TYPE_STANDARD)
            ->setCode(self::SUBSCRIPTION_TYPE_STANDARD)
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

        // SIMULATE RUN
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create condition job
        $this->engine->run(true); // job(cond): created -> scheduled
        $this->dispatcher->handle(); // job(cond): scheduled -> started -> finished
        $this->engine->run(true); // job(cond): deleted, job(email): created
        $this->engine->run(true); // job(email): created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished
        $this->engine->run(true); // job(email): deleted

        // Check email was sent
        $mails = $this->mailsSentTo('test@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals(self::EMAIL_TEMPLATE_SUCCESS, $mails[0]);
    }

    /**
     * Test scenario with TRIGGER -> CONDITION -> MAIL (positive) flow
     */
    public function testUserSourceConditionPositiveFlow()
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
                    'elements' => ['element_condition']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_condition',
                    'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
                    'condition' => [
                        'descendants' => [
                            ['uuid' => 'element_email_pos', 'direction' => 'positive'],
                            ['uuid' => 'element_email_neg', 'direction' => 'negative']
                        ],
                        'conditions' => [
                            'event' => 'user',
                            'version' => 1,
                            'nodes' => [
                                [
                                    'id' => 1,
                                    'key' => 'source',
                                    'values' => [
                                        'selection'=> ['mobile_app'],
                                        'operator' => 'or'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_pos',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_SUCCESS]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email_neg',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => self::EMAIL_TEMPLATE_FAIL]
                ])
            ]
        ]);

        // Create user, trigger scenario
        $this->userManager->addNewUser('test@email.com', false, 'mobile_app', null, false);

        // SIMULATE RUN
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create condition job
        $this->engine->run(true); // job(cond): created -> scheduled
        $this->dispatcher->handle(); // job(cond): scheduled -> started -> finished
        $this->engine->run(true); // job(cond): deleted, job(email): created
        $this->engine->run(true); // job(email): created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished
        $this->engine->run(true); // job(email): deleted

        // Check email was sent
        $mails = $this->mailsSentTo('test@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals(self::EMAIL_TEMPLATE_SUCCESS, $mails[0]);

        // Create other user, trigger scenario
        $this->userManager->addNewUser('test2@email.com', false, 'some_other_source', null, false);

        // SIMULATE RUN
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(true); // process trigger, finish its job and create condition job
        $this->engine->run(true); // job(cond): created -> scheduled
        $this->dispatcher->handle(); // job(cond): scheduled -> started -> finished
        $this->engine->run(true); // job(cond): deleted, job(email): created
        $this->engine->run(true); // job(email): created -> scheduled
        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished
        $this->engine->run(true); // job(email): deleted

        $mails = $this->mailsSentTo('test2@email.com');
        $this->assertCount(1, $mails);
        $this->assertEquals(self::EMAIL_TEMPLATE_FAIL, $mails[0]);
    }
}
