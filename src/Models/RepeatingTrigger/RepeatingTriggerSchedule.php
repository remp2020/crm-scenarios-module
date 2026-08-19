<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Models\RepeatingTrigger;

use DateInterval;
use DateInvalidOperationException;
use DateTimeImmutable;

/**
 * The wall-clock schedule of a single repeating trigger, and when it ticks.
 */
class RepeatingTriggerSchedule
{
    public function __construct(
        private readonly RepeatingTrigger $trigger,
    ) {
    }

    /**
     * Every occurrence of the schedule inside the time frame `[now - window, now]`, oldest first. A window
     * longer than a day holds several occurrences of a daily schedule, so all of them are returned.
     *
     * @param DateTimeImmutable $now
     * @param DateInterval $window How far back to look
     *
     * @return DateTimeImmutable[] empty when the schedule does not tick inside the frame
     *
     * @throws DateInvalidOperationException
     */
    public function findInsideWindow(DateTimeImmutable $now, DateInterval $window): array
    {
        $from = $now->sub($window);

        $occurrences = [];

        // The window spans whole calendar dates, each of which can hold one occurrence of the schedule.
        $lastDate = $now->setTime(0, 0);
        for ($date = $from->setTime(0, 0); $date <= $lastDate; $date = $date->modify('+1 day')) {
            $scheduledDate = $this->buildScheduledDate($date);
            if ($this->matchesSchedule(scheduledOn: $scheduledDate, fromDatetime: $from, toDateTime: $now)) {
                $occurrences[] = $scheduledDate;
            }
        }

        return $occurrences;
    }

    /**
     * The configured wall-clock time on the given date.
     */
    private function buildScheduledDate(DateTimeImmutable $date): DateTimeImmutable
    {
        $time = $this->trigger->time;

        // The time is validated when the trigger is read, so the format always parses.
        return DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $time . ':00',
        ) ?: throw new \LogicException("Time [{$time}] of a repeating trigger is not a valid 'HH:MM' time.");
    }

    private function matchesSchedule(
        DateTimeImmutable $scheduledOn,
        DateTimeImmutable $fromDatetime,
        DateTimeImmutable $toDateTime,
    ): bool {
        $insideWindow = $fromDatetime <= $scheduledOn && $scheduledOn <= $toDateTime;

        return $insideWindow && match ($this->trigger->frequency) {
            RepeatingTriggerFrequencyEnum::Daily => true,
            RepeatingTriggerFrequencyEnum::Weekly => (int) $scheduledOn->format('N') === $this->trigger->day,
            RepeatingTriggerFrequencyEnum::Monthly => (int) $scheduledOn->format('j') === $this->dayOfMonth($scheduledOn),
        };
    }

    /**
     * The configured day of month, clamped to the last day of the given month. A day that month does not
     * have (29-31 in February, 31 in April, ...) fires on its last day, so the trigger fires every month.
     */
    private function dayOfMonth(DateTimeImmutable $scheduledOn): int
    {
        return min((int) $this->trigger->day, (int) $scheduledOn->format('t'));
    }
}
