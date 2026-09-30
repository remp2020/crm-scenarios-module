<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Engine\JobParamsRedactor;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;
use Crm\ScenariosModule\Events\ScenariosGenericEventsManager;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobHistoryRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\ScenariosVersionsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

class JourneyTrackingTest extends BaseTestCase
{
    private UserManager $userManager;
    private JobsRepository $jobsRepository;
    private JobHistoryRepository $jobHistoryRepository;
    private ScenariosRepository $scenariosRepository;
    private ScenariosGenericEventsManager $genericEventManager;
    private ScenariosVersionsRepository $scenariosVersionsRepository;
    private bool $genericEventRegistered = false;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->jobHistoryRepository = $this->getRepository(JobHistoryRepository::class);
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->genericEventManager = $this->inject(ScenariosGenericEventsManager::class);
        $this->scenariosVersionsRepository = $this->getRepository(ScenariosVersionsRepository::class);
    }

    public function testJourneyIdIsPropagatedFromTriggerToAllDescendants(): void
    {
        $this->createScenario();
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job

        $triggerJob = $this->jobsRepository->getAllJobs()->fetch();
        $this->assertNotNull($triggerJob->trigger_id);
        // the trigger job starts the journey, so it is identified by its own id
        $this->assertSame($triggerJob->id, $triggerJob->journey_id);
        $this->assertNull($triggerJob->parent_job_id);

        $journeyId = $triggerJob->id;

        $this->engine->run(3); // trigger: created -> finished -> (generic 1) created -> scheduled

        $firstElementJob = $this->jobOfElement('element_run_generic_1');
        $this->assertSame($journeyId, $firstElementJob->journey_id);
        $this->assertSame($journeyId, $firstElementJob->parent_job_id); // scheduled by the trigger job
        $firstElementJobId = $firstElementJob->id;

        $this->dispatcher->handle(); // job(generic 1): scheduled -> started -> finished
        $this->engine->run(2); // job(generic 1) deleted, both descendants created -> scheduled

        // fan-out: both descendants belong to the same journey and share the same parent
        foreach (['element_run_generic_2', 'element_run_generic_3'] as $elementUuid) {
            $job = $this->jobOfElement($elementUuid);
            $this->assertSame($journeyId, $job->journey_id, "journey id was not propagated to {$elementUuid}");
            $this->assertSame($firstElementJobId, $job->parent_job_id);
        }

        $this->dispatcher->handle(); // descendants: scheduled -> started -> finished
        $this->engine->run(1); // descendants deleted

        $this->assertCount(0, $this->jobsRepository->getAllJobs()->fetchAll());

        // history keeps the whole journey together, including the trigger row
        $historyRows = $this->jobHistoryRepository->getTable()
            ->where('user_id', $user->id)
            ->order('id')
            ->fetchAll();

        $this->assertCount(4, $historyRows);
        foreach ($historyRows as $row) {
            $this->assertSame($journeyId, $row->journey_id);
        }

        $triggerHistory = reset($historyRows);
        $this->assertSame($journeyId, $triggerHistory->job_id); // the journey id is the trigger job id
        $this->assertNull($triggerHistory->parent_job_id);
    }

    public function testSensitiveParamsAreRedactedInHistoryOnly(): void
    {
        $this->createScenario();
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // trigger: created -> finished -> (generic 1) created -> scheduled

        // live jobs keep the password, following elements (e.g. welcome email) need it
        $firstElementJob = $this->jobOfElement('element_run_generic_1');
        $parameters = Json::decode($firstElementJob->parameters, forceArrays: true);
        $this->assertNotEmpty($parameters['password']);
        $this->assertNotSame(JobParamsRedactor::REDACTED, $parameters['password']);

        $this->dispatcher->handle();
        $this->engine->run(2);
        $this->dispatcher->handle();
        $this->engine->run(1);

        $historyRows = $this->jobHistoryRepository->getTable()->where('user_id', $user->id)->fetchAll();
        $this->assertCount(4, $historyRows);
        foreach ($historyRows as $row) {
            $parameters = Json::decode($row->parameters, forceArrays: true);
            $this->assertSame(JobParamsRedactor::REDACTED, $parameters['password']);
            $this->assertSame($user->id, $parameters['user_id']);
        }
    }

    public function testEachTriggerFiringStartsItsOwnJourney(): void
    {
        $this->createScenario();

        $this->userManager->addNewUser('first@email.com', false, 'unknown', null, false);
        $this->userManager->addNewUser('second@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle(); // run Hermes to create both trigger jobs
        $this->engine->run(3); // triggers finished, one generic job created per firing

        $journeyIds = $this->jobsRepository->getAllJobs()->fetchPairs('id', 'journey_id');

        $this->assertCount(2, $journeyIds);
        $this->assertCount(2, array_unique($journeyIds));
        foreach ($journeyIds as $journeyId) {
            $this->assertNotNull($journeyId);
        }
    }

    public function testRetriedJobKeepsItsJourney(): void
    {
        $this->createScenario();
        $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $this->dispatcher->handle();
        $journeyId = $this->jobsRepository->getAllJobs()->fetch()->id;

        $this->engine->run(3);

        $job = $this->jobOfElement('element_run_generic_1');

        // simulate a failure that is allowed to retry - the engine reuses the same row
        $this->jobsRepository->update($job, [
            'state' => JobsRepository::STATE_FAILED,
            'result' => json_encode(['retry' => true]),
        ]);
        $this->engine->run(1);

        $retriedJob = $this->jobsRepository->find($job->id);
        $this->assertSame(1, $retriedJob->retry_count);
        $this->assertSame($journeyId, $retriedJob->journey_id);
    }

    public function testJobsRecordTheScenarioVersionTheyRunOn(): void
    {
        $scenario = $this->createScenario();
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $versionId = $this->scenariosVersionsRepository->current($scenario->id)->id;

        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // trigger done, first element job created under the first version

        $this->assertSame($versionId, $this->jobOfElement('element_run_generic_1')->scenario_version_id);

        $this->dispatcher->handle(); // job(generic 1): scheduled -> started -> finished
        $this->engine->run(2); // job(generic 1) deleted, both descendants created -> scheduled
        $this->dispatcher->handle(); // descendants: scheduled -> started -> finished
        $this->engine->run(1); // descendants deleted

        $historyRows = $this->jobHistoryRepository->getTable()->where('user_id', $user->id)->fetchAll();
        $this->assertCount(4, $historyRows);
        foreach ($historyRows as $row) {
            $this->assertSame($versionId, $row->scenario_version_id);
        }
    }

    public function testOneJourneyCanSpanSeveralScenarioVersions(): void
    {
        $scenario = $this->createScenario();
        $user = $this->userManager->addNewUser('test@email.com', false, 'unknown', null, false);

        $firstVersionId = $this->scenariosVersionsRepository->current($scenario->id)->id;

        $this->dispatcher->handle();
        $triggerJobId = $this->jobsRepository->getAllJobs()->fetch()->id;
        $this->engine->run(3); // trigger done, first element job created under the first version

        $firstElementJob = $this->jobOfElement('element_run_generic_1');
        $this->assertSame($firstVersionId, $firstElementJob->scenario_version_id);
        $firstElementJobId = $firstElementJob->id;

        // the scenario is edited while the user is already inside it
        $this->createScenario($scenario->id, elementName: 'Renamed');
        $secondVersionId = $this->scenariosVersionsRepository->current($scenario->id)->id;
        $this->assertNotSame($firstVersionId, $secondVersionId);

        $this->dispatcher->handle();
        $this->engine->run(2); // descendants are picked up under the new version
        $secondElementJobId = $this->jobOfElement('element_run_generic_2')->id;
        $thirdElementJobId = $this->jobOfElement('element_run_generic_3')->id;
        $this->dispatcher->handle();
        $this->engine->run(1);

        $versionsByJob = $this->jobHistoryRepository->getTable()
            ->where('user_id', $user->id)
            ->fetchPairs('job_id', 'scenario_version_id');

        // one pass through the scenario, but the jobs ran on two different definitions of it
        $journeyIds = $this->jobHistoryRepository->getTable()
            ->where('user_id', $user->id)
            ->fetchPairs(null, 'journey_id');
        $this->assertCount(1, array_unique($journeyIds));
        $this->assertEquals([
            $triggerJobId => $firstVersionId,
            $firstElementJobId => $firstVersionId,
            $secondElementJobId => $secondVersionId,
            $thirdElementJobId => $secondVersionId,
        ], $versionsByJob);
    }

    private function jobOfElement(string $elementUuid): ActiveRow
    {
        $job = $this->jobsRepository->getAllJobs()
            ->where('element_id', $this->elementId($elementUuid))
            ->fetch();

        $this->assertNotNull($job, "no job found for element {$elementUuid}");

        return $job;
    }

    private function registerGenericEvent(): void
    {
        if ($this->genericEventRegistered) {
            return;
        }
        $this->genericEventRegistered = true;

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
    }

    private function createScenario(?int $scenarioId = null, string $elementName = ''): ActiveRow
    {
        $this->registerGenericEvent();

        $data = [
            'name' => 'journey test',
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
                    'name' => $elementName,
                    'id' => 'element_run_generic_1',
                    'type' => ElementsRepository::ELEMENT_TYPE_GENERIC,
                    'generic' => [
                        'code' => 'test_generic_event_code',
                        'descendants' => [
                            ['uuid' => 'element_run_generic_2'],
                            ['uuid' => 'element_run_generic_3'],
                        ],
                    ],
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_run_generic_2',
                    'type' => ElementsRepository::ELEMENT_TYPE_GENERIC,
                    'generic' => ['code' => 'test_generic_event_code'],
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_run_generic_3',
                    'type' => ElementsRepository::ELEMENT_TYPE_GENERIC,
                    'generic' => ['code' => 'test_generic_event_code'],
                ]),
            ],
        ];

        if ($scenarioId !== null) {
            $data['id'] = $scenarioId;
        }

        return $this->scenariosRepository->createOrUpdate($data);
    }
}
