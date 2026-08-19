<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Database\DatabaseTransaction;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerInvalidDataException;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerSchedule;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerScheduleStateEnum;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\RepeatingTriggerScheduleRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Models\SegmentException;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use DateInterval;
use DateTimeImmutable;
use Nette\Database\Table\ActiveRow;
use Nette\Database\UniqueConstraintViolationException;
use Nette\UnexpectedValueException;
use Psr\Log\LoggerInterface;
use Tracy\Debugger;

class RepeatingTriggerGenerator
{
    private const int DEFAULT_SCAN_WINDOW_MINUTES = 20;
    private const int DEFAULT_CATCH_UP_WINDOW_MINUTES = 60;

    private const int DEFAULT_BATCH_SIZE = 500;

    private DateInterval $scanWindow;
    private DateInterval $catchUpWindow;

    private int $batchSize = self::DEFAULT_BATCH_SIZE;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TriggersRepository $triggersRepository,
        private readonly RepeatingTriggerScheduleRepository $repeatingTriggerScheduleRepository,
        private readonly SegmentFactoryInterface $segmentFactory,
        private readonly SegmentsRepository $segmentsRepository,
        private readonly Dispatcher $dispatcher,
        private readonly DatabaseTransaction $databaseTransaction,
    ) {
        $this->scanWindow = new DateInterval('PT' . self::DEFAULT_SCAN_WINDOW_MINUTES . 'M');
        $this->catchUpWindow = new DateInterval('PT' . self::DEFAULT_CATCH_UP_WINDOW_MINUTES . 'M');
    }

    public function setScanWindow(DateInterval $scanWindow): void
    {
        $this->scanWindow = $scanWindow;
    }

    public function setCatchUpWindow(DateInterval $catchUpWindow): void
    {
        $this->catchUpWindow = $catchUpWindow;
    }

    public function setBatchSize(int $batchSize): void
    {
        if ($batchSize < 1) {
            throw new \InvalidArgumentException("Batch size has to be positive, [{$batchSize}] given.");
        }

        $this->batchSize = $batchSize;
    }

    public function generate(DateTimeImmutable $now): array
    {
        $result = [];

        $triggers = $this->triggersRepository->findByType(TriggersRepository::TRIGGER_TYPE_REPEATING);
        foreach ($triggers as $triggerRow) {
            if (!$triggerRow->scenario->enabled) {
                continue;
            }

            try {
                $repeatingTrigger = RepeatingTrigger::fromActiveRow($triggerRow);
            } catch (RepeatingTriggerInvalidDataException $exception) {
                $this->logger->error($exception->getMessage());
                continue;
            }

            $occurrences = (new RepeatingTriggerSchedule($repeatingTrigger))
                ->findInsideWindow($now, $this->catchUpWindow);
            if ($occurrences === []) {
                continue;
            }

            if ($this->hasUnfinishedOccurrence($triggerRow)) {
                continue;
            }

            // A catch-up window longer than a day can hold several occurrences, each of them is claimed
            // and enrolled separately, oldest first.
            foreach ($occurrences as $occurredAt) {
                $jobsCreated = $this->processOccurrence($triggerRow, $repeatingTrigger, $occurredAt, $now);
                if ($jobsCreated !== null) {
                    $result[$triggerRow->id][$occurredAt->format('Y-m-d H:i')] = $jobsCreated;
                }
            }
        }

        return $result;
    }

    /**
     * A previous run (or a concurrent one) may still be enrolling an occurrence of this trigger. S
     */
    private function hasUnfinishedOccurrence(ActiveRow $triggerRow): bool
    {
        $runningOccurrence = $this->repeatingTriggerScheduleRepository->getTable()
            ->where([
                'trigger_id' => $triggerRow->id,
                'state' => RepeatingTriggerScheduleStateEnum::Running->value,
            ])
            ->order('occurred_at')
            ->fetch();

        if ($runningOccurrence === null) {
            return false;
        }

        $errorMessage = sprintf(
            'Repeating trigger [%s] is skipped, its occurrence [%s] is still being enrolled since [%s], '
            . '%d users enrolled so far',
            $triggerRow->uuid,
            $runningOccurrence->occurred_at->format('Y-m-d H:i'),
            $runningOccurrence->created_at->format('Y-m-d H:i:s'),
            $runningOccurrence->jobs_created,
        );
        $this->logger->warning($errorMessage);
        Debugger::log($errorMessage, Debugger::WARNING);

        return true;
    }

    /**
     * @return int|null Number of created jobs, or NULL when the occurrence was not processed by this run
     */
    private function processOccurrence(
        ActiveRow $trigger,
        RepeatingTrigger $repeatingTrigger,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $now,
    ): ?int {
        $segmentCode = $repeatingTrigger->segmentCode;

        $segmentRow = $this->segmentsRepository->findByCode($segmentCode);
        if (!$segmentRow || $segmentRow->table_name !== RepeatingTrigger::SEGMENT_TABLE) {
            $errorMessage = sprintf(
                'Repeating trigger [%s] refers to a missing segment or one not based on the [%s] table: [%s]',
                $trigger->uuid,
                RepeatingTrigger::SEGMENT_TABLE,
                $segmentCode,
            );
            $this->logger->error($errorMessage);
            Debugger::log($errorMessage, Debugger::ERROR);

            return null;
        }

        try {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
        } catch (UnexpectedValueException | SegmentException $exception) {
            $errorMessage = sprintf(
                'Repeating trigger [%s] refers to an unusable segment [%s]: %s',
                $trigger->uuid,
                $segmentCode,
                $exception->getMessage(),
            );
            $this->logger->error($errorMessage);
            Debugger::log($errorMessage, Debugger::EXCEPTION);

            return null;
        }

        $triggerOccurrence = $this->claimTriggerOccurrence($trigger, $occurredAt, $now);
        if ($triggerOccurrence === null) {
            return null;
        }

        $batch = [];
        try {
            // $triggerOccurrence is captured by reference: advancing the marker replaces the row with a fresh one.
            $segment->process(function ($row) use ($trigger, $occurredAt, &$triggerOccurrence, &$batch): void {
                $batch[] = (int) $row->id;
                if (count($batch) >= $this->batchSize) {
                    $this->flushBatch($trigger, $triggerOccurrence, $occurredAt, $batch);
                    $batch = [];
                }
            });

            if ($batch !== []) {
                $this->flushBatch($trigger, $triggerOccurrence, $occurredAt, $batch);
            }
        } catch (\Throwable $exception) {
            $this->repeatingTriggerScheduleRepository->fail($triggerOccurrence);
            $errorMessage = sprintf(
                'Repeating trigger [%s] failed while enrolling segment [%s] for occurrence [%s], '
                . '%d users enrolled before the failure: %s',
                $trigger->uuid,
                $segmentCode,
                $occurredAt->format('Y-m-d H:i'),
                $triggerOccurrence->jobs_created,
                $exception->getMessage(),
            );
            $this->logger->error($errorMessage);
            Debugger::log($errorMessage, Debugger::EXCEPTION);

            return null;
        }

        $this->repeatingTriggerScheduleRepository->finish($triggerOccurrence);

        return $triggerOccurrence->jobs_created;
    }

    private function flushBatch(
        ActiveRow $triggerRow,
        ActiveRow &$occurrence,
        DateTimeImmutable $occurredAt,
        array $userIds,
    ): void {
        $context = [JobsRepository::CONTEXT_REPEATING => $occurredAt->format('Y-m-d H:i')];

        $this->databaseTransaction->wrap(function () use ($triggerRow, &$occurrence, $userIds, $context): void {
            foreach ($userIds as $userId) {
                $this->dispatcher->dispatchTrigger($triggerRow, $userId, [], $context);
            }

            $this->repeatingTriggerScheduleRepository->advance($occurrence, count($userIds));
        });
    }

    private function claimTriggerOccurrence(
        ActiveRow $triggerRow,
        DateTimeImmutable $occurredAt,
        DateTimeImmutable $now,
    ): ?ActiveRow {
        if ($this->repeatingTriggerScheduleRepository->findByTriggerAndOccurrence($triggerRow->id, $occurredAt)) {
            return null;
        }

        // Too late to fire. Record the miss so that it stays visible but never replay it.
        if ($occurredAt->getTimestamp() < $now->sub($this->scanWindow)->getTimestamp()) {
            try {
                $this->repeatingTriggerScheduleRepository->recordSkipped($triggerRow->id, $occurredAt);
            } catch (UniqueConstraintViolationException) {
                // Recorded by a concurrent generator in the meantime.
            }
            return null;
        }

        try {
            return $this->repeatingTriggerScheduleRepository->claim($triggerRow->id, $occurredAt);
        } catch (UniqueConstraintViolationException) {
            // a concurrent generator claimed it between the lookup and the insert
        }

        return null;
    }
}
