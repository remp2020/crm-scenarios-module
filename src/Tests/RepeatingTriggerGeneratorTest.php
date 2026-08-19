<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Database\DatabaseTransaction;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Events\RepeatingTriggerGenerator;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerScheduleStateEnum;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\RepeatingTriggerScheduleRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggerStatsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use DateInterval;
use DateTimeImmutable;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Psr\Log\NullLogger;
use RuntimeException;

class RepeatingTriggerGeneratorTest extends BaseTestCase
{
    use RepeatingTriggerFixturesTrait;

    /** 2026-08-03 is a Monday, 2026-08-04 a Tuesday. */
    private const MONDAY_MORNING = '2026-08-03 09:05:00';
    private const TUESDAY_MORNING = '2026-08-04 09:05:00';

    private RepeatingTriggerGenerator $generator;

    private JobsRepository $jobsRepository;

    private RepeatingTriggerScheduleRepository $repeatingTriggerScheduleRepository;

    private ScenariosRepository $scenariosRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->repeatingTriggerScheduleRepository = $this->getRepository(RepeatingTriggerScheduleRepository::class);
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);

        $this->generator = $this->makeGenerator();

        $this->addUsersSegment('tests_all_users');
    }

    private function makeGenerator(?Dispatcher $dispatcher = null): RepeatingTriggerGenerator
    {
        return new RepeatingTriggerGenerator(
            new NullLogger(),
            $this->getRepository(TriggersRepository::class),
            $this->repeatingTriggerScheduleRepository,
            $this->inject(SegmentFactoryInterface::class),
            $this->getRepository(SegmentsRepository::class),
            $dispatcher ?? $this->inject(Dispatcher::class),
            $this->inject(DatabaseTransaction::class),
        );
    }

    /**
     * A dispatcher that throws on its n-th call, standing in for a generator that dies mid-enrollment.
     */
    private function dispatcherFailingOnCall(int $failOnCall): Dispatcher
    {
        return new class (
            $this->getRepository(JobsRepository::class),
            $this->scenariosRepository,
            $failOnCall,
        ) extends Dispatcher {
            private int $calls = 0;

            public function __construct(
                JobsRepository $jobsRepository,
                ScenariosRepository $scenariosRepository,
                private readonly int $failOnCall,
            ) {
                parent::__construct($jobsRepository, $scenariosRepository);
            }

            public function dispatchTrigger(
                ActiveRow $triggerRow,
                int $userId,
                array $params,
                ?array $context = null,
            ): void {
                $this->calls++;
                if ($this->calls === $this->failOnCall) {
                    throw new RuntimeException('dispatching failed');
                }

                parent::dispatchTrigger($triggerRow, $userId, $params, $context);
            }
        };
    }

    public function testEnrollsEverySegmentUser()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame(3, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(
            [$this->triggerId('trigger_repeating') => ['2026-08-03 09:00' => 3]],
            $result,
        );

        $occurrence = $this->repeatingTriggerScheduleRepository->getTable()->fetch();
        $this->assertSame(RepeatingTriggerScheduleStateEnum::Finished->value, $occurrence->state);
        $this->assertSame(3, $occurrence->jobs_created);
        $this->assertSame('2026-08-03 09:00:00', $occurrence->occurred_at->format('Y-m-d H:i:s'));
    }

    public function testSameOccurrenceIsNotEnrolledTwice()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        // Overlapping ticks see the same occurrence for as long as it stays inside the scan window.
        $this->generator->generate(new DateTimeImmutable('2026-08-03 09:03:00'));
        $secondRun = $this->generator->generate(new DateTimeImmutable('2026-08-03 09:06:00'));
        $this->generator->generate(new DateTimeImmutable('2026-08-03 09:09:00'));

        $this->assertSame([], $secondRun);
        $this->assertSame(3, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(1, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testNextOccurrenceEnrollsTheSameUsersAgain()
    {
        // The users stay in the segment, so they have to be enrolled on every occurrence.
        $this->addRepeatingScenario(['frequency' => 'daily', 'time' => '09:00']);
        $this->addUsers(2);

        $this->generator->generate(new DateTimeImmutable('2026-08-03 09:05:00'));
        $this->generator->generate(new DateTimeImmutable('2026-08-04 09:05:00'));

        $this->assertSame(4, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(2, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testWindowLongerThanADayEnrollsEveryOccurrenceItHolds()
    {
        $this->addRepeatingScenario(['frequency' => 'daily', 'time' => '09:00']);
        $this->addUsers(2);

        // Three days back: the occurrences of 08-01, 08-02 and 08-03 all fall inside both windows.
        $this->generator->setCatchUpWindow(new DateInterval('P3D'));
        $this->generator->setScanWindow(new DateInterval('P3D'));

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame(
            [$this->triggerId('trigger_repeating') => [
                '2026-08-01 09:00' => 2,
                '2026-08-02 09:00' => 2,
                '2026-08-03 09:00' => 2,
            ]],
            $result,
        );
        $this->assertSame(6, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(3, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testOccurrencesOfALongWindowOlderThanTheScanWindowAreSkipped()
    {
        $this->addRepeatingScenario(['frequency' => 'daily', 'time' => '09:00']);
        $this->addUsers(2);

        // The catch-up window decides which occurrences are seen, the scan window which of them may still
        // fire. Only today's occurrence is young enough, the two older ones are recorded as skipped.
        $this->generator->setCatchUpWindow(new DateInterval('P3D'));

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame(
            [$this->triggerId('trigger_repeating') => ['2026-08-03 09:00' => 2]],
            $result,
        );
        $this->assertSame(2, $this->jobsRepository->getAllJobs()->count('*'));

        $states = [];
        foreach ($this->repeatingTriggerScheduleRepository->getTable()->order('occurred_at') as $occurrence) {
            $states[$occurrence->occurred_at->format('Y-m-d H:i')] = $occurrence->state;
        }
        $this->assertSame([
            '2026-08-01 09:00' => RepeatingTriggerScheduleStateEnum::Skipped->value,
            '2026-08-02 09:00' => RepeatingTriggerScheduleStateEnum::Skipped->value,
            '2026-08-03 09:00' => RepeatingTriggerScheduleStateEnum::Finished->value,
        ], $states);
    }

    public function testWrongWeekdayEnrollsNobody()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $result = $this->generator->generate(new DateTimeImmutable(self::TUESDAY_MORNING));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(0, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testDisabledScenarioEnrollsNobodyAndClaimsNothing()
    {
        $scenario = $this->addRepeatingScenario();
        $this->scenariosRepository->setEnabled($scenario, false);
        $this->addUsers(3);

        $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
        // No occurrence row at all, so re-enabling later does not look like a processed occurrence.
        $this->assertSame(0, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testOccurrenceMissedForTooLongIsSkipped()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        // Five minutes late: outside scan window
        $this->generator->setScanWindow(new DateInterval('PT3M'));
        $this->generator->setCatchUpWindow(new DateInterval('PT10M'));
        $result = $this->generator->generate(new DateTimeImmutable('2026-08-03 09:05:00'));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));

        $occurrence = $this->repeatingTriggerScheduleRepository->getTable()->fetch();
        $this->assertSame(RepeatingTriggerScheduleStateEnum::Skipped->value, $occurrence->state);
        $this->assertSame(0, $occurrence->jobs_created);
    }

    public function testOccurrenceOlderThanTheCatchUpWindowIsNotSeenAtAll()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        // Almost two hours late, well past the 60 minute catch-up window.
        $result = $this->generator->generate(new DateTimeImmutable('2026-08-03 10:55:00'));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(0, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testJobsCarryTheOccurrenceInTheirContext()
    {
        $this->addRepeatingScenario();
        $this->addUsers(1);

        $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $job = $this->jobsRepository->getAllJobs()->fetch();
        $this->assertSame(
            ['repeating' => '2026-08-03 09:00'],
            json_decode($job->context, true),
        );
        $this->assertArrayHasKey('user_id', json_decode($job->parameters, true));
    }

    public function testEngineProcessesJobsOfARepeatingTrigger()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        // The engine is trigger-type agnostic: it only needs a job with a trigger_id and a user_id.
        $this->engine->run(3);

        $triggerId = $this->triggerId('trigger_repeating');
        $triggerStats = $this->getRepository(TriggerStatsRepository::class)
            ->countsForTriggers([$triggerId], new DateTime('-1 hour'));

        $this->assertSame(3, $triggerStats[$triggerId][JobsRepository::STATE_FINISHED]);
    }

    public function testTriggerWithAStillRunningOccurrenceIsNotProcessedAgain()
    {
        $this->addRepeatingScenario(['frequency' => 'daily', 'time' => '09:00']);
        $this->addUsers(2);

        // Yesterday's enrollment is still in flight (a long segment, a slow run, ...).
        $this->repeatingTriggerScheduleRepository->claim(
            $this->triggerId('trigger_repeating'),
            new DateTime('2026-08-02 09:00:00'),
        );

        $result = $this->generator->generate(new DateTimeImmutable('2026-08-03 09:05:00'));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
        // Today's occurrence is left unclaimed, so it still fires once the running one is done.
        $this->assertSame(1, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testTriggerIsProcessedAgainOnceTheRunningOccurrenceEnds()
    {
        $this->addRepeatingScenario(['frequency' => 'daily', 'time' => '09:00']);
        $this->addUsers(2);

        $runningOccurrence = $this->repeatingTriggerScheduleRepository->claim(
            $this->triggerId('trigger_repeating'),
            new DateTime('2026-08-02 09:00:00'),
        );
        $this->generator->generate(new DateTimeImmutable('2026-08-03 09:05:00'));

        $this->repeatingTriggerScheduleRepository->finish($runningOccurrence);
        $result = $this->generator->generate(new DateTimeImmutable('2026-08-03 09:10:00'));

        $this->assertSame(
            [$this->triggerId('trigger_repeating') => ['2026-08-03 09:00' => 2]],
            $result,
        );
        $this->assertSame(2, $this->jobsRepository->getAllJobs()->count('*'));
    }

    public function testSegmentChangedToAnotherTableAfterSavingIsRefused()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        // The segment is validated on save, but it can be edited afterwards. Enrolling rows of another
        // table would treat foreign IDs as user IDs.
        $segmentsRepository = $this->getRepository(SegmentsRepository::class);
        $segment = $segmentsRepository->findByCode('tests_all_users');
        $segmentsRepository->update($segment, ['table_name' => 'subscriptions']);

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
        // Nothing was claimed either, so fixing the segment lets the occurrence still fire.
        $this->assertSame(0, $this->repeatingTriggerScheduleRepository->getTable()->count('*'));
    }

    public function testDeletedSegmentIsRefused()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $segmentsRepository = $this->getRepository(SegmentsRepository::class);
        $segmentsRepository->softDelete($segmentsRepository->findByCode('tests_all_users'));

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
    }

    public function testEnrollmentIsSplitIntoBatches()
    {
        $this->addRepeatingScenario();
        $this->addUsers(5);
        $this->generator->setBatchSize(2);

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame(5, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame([$this->triggerId('trigger_repeating') => ['2026-08-03 09:00' => 5]], $result);

        $occurrence = $this->repeatingTriggerScheduleRepository->getTable()->fetch();
        $this->assertSame(RepeatingTriggerScheduleStateEnum::Finished->value, $occurrence->state);
        $this->assertSame(5, $occurrence->jobs_created);
    }

    public function testFailureMidwayMarksTheOccurrenceFailed()
    {
        $this->addRepeatingScenario();
        $this->addUsers(5);

        // Batches are [u1,u2], [u3,u4], [u5]; the 5th dispatch is the whole third batch.
        $generator = $this->makeGenerator($this->dispatcherFailingOnCall(5));
        $generator->setBatchSize(2);

        $result = $generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        // The failed occurrence is not reported as processed.
        $this->assertSame([], $result);

        $this->assertSame(4, $this->jobsRepository->getAllJobs()->count('*'));

        // The occurrence is closed rather than left open, and records how far it got.
        $occurrence = $this->repeatingTriggerScheduleRepository->getTable()->fetch();
        $this->assertSame(RepeatingTriggerScheduleStateEnum::Failed->value, $occurrence->state);
        $this->assertSame(4, $occurrence->jobs_created);
        $this->assertNotNull($occurrence->finished_at);
    }

    public function testFailedOccurrenceIsNeverRetried()
    {
        $this->addRepeatingScenario();
        $this->addUsers(5);

        $failing = $this->makeGenerator($this->dispatcherFailingOnCall(5));
        $failing->setBatchSize(2);
        $failing->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        // However old the failure is, a healthy generator must leave it alone - re-running it would
        // enroll the four users that were already enrolled a second time.
        $this->ageOccurrence($this->repeatingTriggerScheduleRepository->getTable()->fetch(), '-1 day');

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame([], $result);
        $this->assertSame(4, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(
            RepeatingTriggerScheduleStateEnum::Failed->value,
            $this->repeatingTriggerScheduleRepository->getTable()->fetch()->state,
        );
    }

    public function testOccurrenceLeftRunningByAKilledGeneratorIsNeverRetried()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        // A generator killed outright (SIGKILL, OOM) cannot mark its occurrence, so the row stays
        // `running` forever. It is still never picked up again.
        $this->repeatingTriggerScheduleRepository->claim(
            $this->triggerId('trigger_repeating'),
            new DateTimeImmutable('2026-08-03 09:00:00'),
        );
        $this->ageOccurrence($this->repeatingTriggerScheduleRepository->getTable()->fetch(), '-1 day');

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame([], $result);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
    }

    public function testFinishedOccurrenceIsNeverResumed()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);
        $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        // Even an ancient finished occurrence must not be picked up again.
        $this->ageOccurrence($this->repeatingTriggerScheduleRepository->getTable()->fetch(), '-1 day');

        $result = $this->generator->generate(new DateTimeImmutable(self::MONDAY_MORNING));

        $this->assertSame([], $result);
        $this->assertSame(3, $this->jobsRepository->getAllJobs()->count('*'));
    }

    private function ageOccurrence(ActiveRow $occurrence, string $updatedAt): void
    {
        $this->repeatingTriggerScheduleRepository->getTable()
            ->where('id', $occurrence->id)
            ->update(['updated_at' => new DateTime($updatedAt)]);
    }
}
