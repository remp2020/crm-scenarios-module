<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ScenariosModule\Api\ScenariosJourneyJobsApiHandler;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Utils\DateTime;

class ScenariosJourneyJobsApiHandlerTest extends BaseTestCase
{
    use ApiTestTrait;

    private UserManager $userManager;
    private JobsRepository $jobsRepository;
    private ScenariosRepository $scenariosRepository;
    private ScenariosJourneyJobsApiHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->handler = $this->inject(ScenariosJourneyJobsApiHandler::class);
    }

    public function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testRunningWaitIsListedAfterFinishedJobs(): void
    {
        $scenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'test',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10],
                ]),
            ],
        ]);
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // trigger job created
        $journeyId = $this->jobsRepository->getAllJobs()->fetch()->id;
        $this->engine->run(4); // trigger finished and moved to history, wait job started

        $waitJob = $this->jobsRepository->getStartedJobs()->fetch();
        $this->assertNotNull($waitJob);

        $jobs = $this->fetchJobs($scenario->id, $journeyId);

        $this->assertCount(2, $jobs);

        $this->assertSame('trigger', $jobs[0]['node']['kind']);
        $this->assertFalse($jobs[0]['in_progress']);
        $this->assertNull($jobs[0]['wait_until']);

        $this->assertSame($waitJob->id, $jobs[1]['job_id']);
        $this->assertSame($journeyId, $jobs[1]['parent_job_id']);
        $this->assertSame('element_wait', $jobs[1]['node']['uuid']);
        $this->assertSame(JobsRepository::STATE_STARTED, $jobs[1]['state']);
        $this->assertTrue($jobs[1]['in_progress']);
        $this->assertSame(
            DateTime::from($waitJob->started_at)->modify('+10 minutes')->format(DATE_RFC3339),
            $jobs[1]['wait_until'],
        );
    }

    public function testFinishedJourneyHasNoJobsInProgress(): void
    {
        $scenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'test',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10],
                ]),
            ],
        ]);
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle();
        $journeyId = $this->jobsRepository->getAllJobs()->fetch()->id;
        $this->engine->run(4);
        // dummy Hermes driver ignores the delay and finishes the wait right away
        $this->dispatcher->handle();
        $this->engine->run(1);

        $jobs = $this->fetchJobs($scenario->id, $journeyId);

        $this->assertCount(2, $jobs);
        foreach ($jobs as $job) {
            $this->assertFalse($job['in_progress']);
            $this->assertNull($job['wait_until']);
        }
    }

    private function fetchJobs(int $scenarioId, int $journeyId): array
    {
        $_GET = ['id' => (string) $scenarioId, 'journey_id' => (string) $journeyId];
        $response = $this->runJsonApi($this->handler);
        $this->assertSame(200, $response->getCode());

        return $response->getPayload()['jobs'];
    }
}
