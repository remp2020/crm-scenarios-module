<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\UsersModule\Models\Auth\UserManager;

class ElementDeletedInRunningScenarioTest extends BaseTestCase
{
    /** @var UserManager */
    private $userManager;

    /** @var ScenariosRepository */
    private $scenariosRepository;

    /** @var TriggersRepository */
    private $triggersRepository;

    /** @var ElementsRepository */
    private $elementsRepository;

    /** @var JobsRepository */
    private $jobsRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->triggersRepository = $this->getRepository(TriggersRepository::class);
        $this->elementsRepository = $this->getRepository(ElementsRepository::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);

        $this->userManager = $this->inject(UserManager::class);
    }

    public function testTriggerUserRegisteredScenario()
    {
        $scenario = $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_email'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_email',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code'],
                ]),
            ],
        ]);

        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(2); // process trigger, finish its job and create email job

        // Update replaces email element
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'id' => $scenario->id,
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_email2'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_email2',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code'],
                ]),
            ],
        ]);

        $this->engine->run(1); // email job should be scheduled
        $this->dispatcher->handle(); // run email job in Hermes
        $this->engine->run(1); // job should be deleted even if element is finished, WARNING log is created

        $this->assertCount(0, $this->jobsRepository->getFinishedJobs()->fetchAll());
    }
}
