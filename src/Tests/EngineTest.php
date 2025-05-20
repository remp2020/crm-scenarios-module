<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\ScenariosModule\Events\ScenariosGenericEventsManager;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use DateTime;

class EngineTest extends BaseTestCase
{
    private UserManager $userManager;
    private JobsRepository $jobsRepository;
    private ScenariosRepository $scenariosRepository;
    private ScenariosGenericEventsManager $genericEventManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->genericEventManager = $this->inject(ScenariosGenericEventsManager::class);
    }

    public function testStandardFlow()
    {
        $scenario = $this->createScenario();
        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(3); // process trigger: (trigger) created -> finished -> (element) created -> scheduled

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        $this->dispatcher->handle(); // job(generic): scheduled -> started -> finished

        $this->assertCount(1, $this->jobsRepository->getFinishedJobs()->fetchAll());

        $this->engine->run(2); // process element: (element) finished -> (next element) created -> scheduled

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        $this->dispatcher->handle(); // job(generic): scheduled -> started -> finished

        $this->assertCount(1, $this->jobsRepository->getFinishedJobs()->fetchAll());

        $this->engine->run(1); // process element: finish its job

        $this->assertCount(0, $this->jobsRepository->getUnprocessedJobs()->fetchAll());
        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());
    }

    public function testDisabledScenarioStopsExecuting()
    {
        $scenario = $this->createScenario();
        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(2); // process trigger: (trigger) created -> finished -> (element) created

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        // DISABLE SCENARIO
        $this->scenariosRepository->setEnabled($scenario, false);

        $this->engine->run(1); // should do nothing

        $this->assertCount(0, $this->jobsRepository->getScheduledJobs()->fetchAll()); // do not process disabled scenario jobs
        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        // ENABLE SCENARIO
        $this->scenariosRepository->setEnabled($scenario);

        $this->engine->run(1); // job(generic): created -> scheduled

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll()); // continue process
    }

    public function testDeletedScenarioDeleteJobs()
    {
        $scenario = $this->createScenario();
        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(2); // process trigger: (trigger) created -> finished -> (element) created

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        // DELETE SCENARIO
        $this->scenariosRepository->softDelete($scenario);

        $this->engine->run(1);

        $this->assertCount(0, $this->jobsRepository->getUnprocessedJobs()->fetchAll());
        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());
    }

    public function testDeleteScenarioWhileJobRunningDeleteJobAfterProcessed()
    {
        $scenario = $this->createScenario();
        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(3); // process trigger: (trigger) created -> finished -> (element) created -> scheduled

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        // DELETE SCENARIO
        $this->scenariosRepository->softDelete($scenario);

        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll()); // do not delete running jobs

        $this->dispatcher->handle(); // job(generic): scheduled -> started -> finished

        $this->engine->run(1); // job(generic): DELETE, do not schedule next job

        $this->assertCount(0, $this->jobsRepository->getUnprocessedJobs()->fetchAll());
        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());
    }

    public function testRestoredScenarioDoNotProcessJobsCreatedBeforeRestore()
    {
        $scenario = $this->createScenario();
        // Add user, which triggers scenario
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->assertCount(1, $this->jobsRepository->getUnprocessedJobs()->fetchAll());

        $this->engine->run(3); // process trigger: (trigger) created -> finished -> (element) created -> scheduled
        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll());

        // DELETE SCENARIO
        $this->scenariosRepository->softDelete($scenario);
        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll()); // do not delete running jobs

        // RESTORE SCENARIO
        $this->scenariosRepository->restoreScenario($scenario);

        $scenario = $this->scenariosRepository->find($scenario->id); // get actual scenario row
        $this->assertNotNull($scenario->restored_at); // check if restored_at is set
        $this->scenariosRepository->update($scenario, ['restored_at' => new DateTime('now + 5 minutes')]); // ensure scenario restored_at is after job created_at

        $this->scenariosRepository->setEnabled($scenario);
        $this->assertCount(1, $this->jobsRepository->getScheduledJobs()->fetchAll()); // do not delete running jobs

        $this->dispatcher->handle(); // job(generic): scheduled -> started -> finished
        $this->assertCount(1, $this->jobsRepository->getFinishedJobs()->fetchAll());

        $this->engine->run(1); // DELETE JOB CREATED BEFORE RESTORE

        $this->assertCount(0, $this->jobsRepository->getUnprocessedJobs()->fetchAll()); // do not schedule next job
        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());
    }

    private function createScenario()
    {
        $this->genericEventManager->register('test_generic_event_code', new class implements ScenarioGenericEventInterface {
            public function getLabel(): string
            {
                return 'test';
            }

            public function getParams(): array
            {
                return [];
            }

            public function createEvents($options, $params): array
            {
                return [];
            }
        });

        return $this->scenariosRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_run_generic_1'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_run_generic_1',
                    'type' => ElementsRepository::ELEMENT_TYPE_GENERIC,
                    'generic' => [
                        'code' => 'test_generic_event_code',
                        'descendants' => [
                            ['uuid' => 'element_run_generic_2'],
                        ],
                    ],
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_run_generic_2',
                    'type' => ElementsRepository::ELEMENT_TYPE_GENERIC,
                    'generic' => ['code' => 'test_generic_event_code'],
                ]),
            ],
        ]);
    }
}
