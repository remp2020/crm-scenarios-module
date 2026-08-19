<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerScheduleStateEnum;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\RepeatingTriggerScheduleRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use DateTimeImmutable;
use Nette\Database\UniqueConstraintViolationException;

class RepeatingTriggerScheduleRepositoryTest extends BaseTestCase
{
    private RepeatingTriggerScheduleRepository $scheduleRepository;

    private int $triggerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduleRepository = $this->getRepository(RepeatingTriggerScheduleRepository::class);

        $segmentGroup = $this->getRepository(SegmentGroupsRepository::class)->add('Test group', 'test_group');
        $this->getRepository(SegmentsRepository::class)->add(
            'All users (tests)',
            1,
            'tests_all_users',
            'users',
            'users.id',
            'SELECT %fields% FROM %table% WHERE %where%',
            $segmentGroup,
        );

        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'repeating scenario',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_REPEATING,
                    'id' => 'trigger_repeating',
                    'event' => ['code' => RepeatingTrigger::EVENT_CODE],
                    'options' => [
                        'segment_code' => 'tests_all_users',
                        'frequency' => 'weekly',
                        'day' => 1,
                        'time' => '09:00',
                    ],
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

        $this->triggerId = $this->triggerId('trigger_repeating');
    }

    public function testClaimStoresRunningOccurrence()
    {
        $occurredAt = new DateTimeImmutable('2026-08-03 09:00:00');

        $occurrence = $this->scheduleRepository->claim($this->triggerId, $occurredAt);

        $this->assertSame(RepeatingTriggerScheduleStateEnum::Running->value, $occurrence->state);
        $this->assertSame(0, $occurrence->jobs_created);
        $this->assertNull($occurrence->finished_at);
        $this->assertSame('2026-08-03 09:00:00', $occurrence->occurred_at->format('Y-m-d H:i:s'));
    }

    public function testClaimingTheSameOccurrenceTwiceIsRejected()
    {
        $occurredAt = new DateTimeImmutable('2026-08-03 09:00:00');
        $this->scheduleRepository->claim($this->triggerId, $occurredAt);

        // This is the lock of the whole design: the second generator is rejected by the unique index
        // instead of enrolling the segment users again.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->scheduleRepository->claim($this->triggerId, $occurredAt);
    }

    public function testDifferentOccurrencesOfTheSameTriggerCanBothBeClaimed()
    {
        $this->scheduleRepository->claim($this->triggerId, new DateTimeImmutable('2026-08-03 09:00:00'));
        $this->scheduleRepository->claim($this->triggerId, new DateTimeImmutable('2026-08-06 09:00:00'));

        $this->assertSame(2, $this->scheduleRepository->getTable()->count('*'));
    }

    public function testFindByTriggerAndOccurrence()
    {
        $occurredAt = new DateTimeImmutable('2026-08-03 09:00:00');
        $claimed = $this->scheduleRepository->claim($this->triggerId, $occurredAt);

        $found = $this->scheduleRepository->findByTriggerAndOccurrence($this->triggerId, $occurredAt);
        $this->assertSame($claimed->id, $found->id);

        $missing = $this->scheduleRepository->findByTriggerAndOccurrence(
            $this->triggerId,
            new DateTimeImmutable('2026-08-06 09:00:00'),
        );
        $this->assertNull($missing);
    }

    public function testAdvanceAccumulatesJobCount()
    {
        $occurrence = $this->scheduleRepository->claim(
            $this->triggerId,
            new DateTimeImmutable('2026-08-03 09:00:00'),
        );

        // update() refreshes the passed row, so consecutive batches accumulate.
        $this->scheduleRepository->advance($occurrence, 500);
        $this->scheduleRepository->advance($occurrence, 440);

        $reloaded = $this->scheduleRepository->find($occurrence->id);
        $this->assertSame(940, $reloaded->jobs_created);
        $this->assertSame(RepeatingTriggerScheduleStateEnum::Running->value, $reloaded->state);
    }

    public function testFinishMarksOccurrenceFinished()
    {
        $occurrence = $this->scheduleRepository->claim(
            $this->triggerId,
            new DateTimeImmutable('2026-08-03 09:00:00'),
        );

        $this->scheduleRepository->finish($occurrence);

        $this->assertSame(RepeatingTriggerScheduleStateEnum::Finished->value, $occurrence->state);
        $this->assertNotNull($occurrence->finished_at);
    }

    public function testFailMarksOccurrenceFailedAndKeepsItsProgress()
    {
        $occurredAt = new DateTimeImmutable('2026-08-03 09:00:00');
        $occurrence = $this->scheduleRepository->claim($this->triggerId, $occurredAt);

        $this->scheduleRepository->advance($occurrence, 40);

        $this->scheduleRepository->fail($occurrence);

        $this->assertSame(RepeatingTriggerScheduleStateEnum::Failed->value, $occurrence->state);
        $this->assertNotNull($occurrence->finished_at);

        // The partial run stays readable: this is how far the enrollment got before it broke.
        $this->assertSame(40, $occurrence->jobs_created);
    }

    public function testFailedOccurrenceIsTerminal()
    {
        $occurredAt = new DateTimeImmutable('2026-08-03 09:00:00');
        $occurrence = $this->scheduleRepository->claim($this->triggerId, $occurredAt);
        $this->scheduleRepository->fail($occurrence);

        // A failed occurrence keeps occupying the unique key, so it can never be claimed again.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->scheduleRepository->claim($this->triggerId, $occurredAt);
    }

    public function testRecordSkippedIsTerminal()
    {
        $occurredAt = new DateTimeImmutable('2026-08-03 09:00:00');

        $occurrence = $this->scheduleRepository->recordSkipped($this->triggerId, $occurredAt);

        $this->assertSame(RepeatingTriggerScheduleStateEnum::Skipped->value, $occurrence->state);
        $this->assertSame(0, $occurrence->jobs_created);
        $this->assertNotNull($occurrence->finished_at);

        // A skipped occurrence still occupies the unique key, so it can never be retried.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->scheduleRepository->claim($this->triggerId, $occurredAt);
    }

    public function testDeletingTriggerCascadesToOccurrences()
    {
        $this->scheduleRepository->claim($this->triggerId, new DateTimeImmutable('2026-08-03 09:00:00'));

        $this->getRepository(TriggersRepository::class)->getTable()->where('id', $this->triggerId)->delete();

        $this->assertSame(0, $this->scheduleRepository->getTable()->count('*'));
    }
}
