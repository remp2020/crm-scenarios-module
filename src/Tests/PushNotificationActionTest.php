<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\UsersModule\Auth\UserManager;

class PushNotificationActionTest extends BaseTestCase
{
    public function testPushNotificationActionTriggered(): void
    {
        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);

        $scenariosRepository->createOrUpdate([
            'name' => 'test_generic',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_push_notification']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_push_notification',
                    'type' => ElementsRepository::ELEMENT_TYPE_PUSH_NOTIFICATION,
                    ElementsRepository::ELEMENT_TYPE_PUSH_NOTIFICATION => [
                        'template' => 'test_template',
                        'application' => 'test_application',
                    ]
                ]),
            ]
        ]);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userManager->addNewUser('test@test.sk');

        /** @var JobsRepository $scenariosJobsRepository */
        $scenariosJobsRepository = $this->getRepository(JobsRepository::class);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // process trigger, finish its job and create+schedule condition job

        $this->assertEquals(1, $scenariosJobsRepository->getScheduledJobs()->count('*'));

        $this->dispatcher->handle(); // run Hermes to handle push notification job

        $this->assertEquals(1, $scenariosJobsRepository->getAllJobs()->count('*'));
        $this->assertEquals(1, $scenariosJobsRepository->getFinishedJobs()->count('*'));
    }
}
