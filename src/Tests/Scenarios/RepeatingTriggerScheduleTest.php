<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerSchedule;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepeatingTriggerScheduleTest extends TestCase
{
    #[DataProvider('occurrencesProvider')]
    public function testOccurrencesInsideTheWindow(
        RepeatingTrigger $trigger,
        string $now,
        int $windowMinutes,
        array $expected,
    ): void {
        $occurrences = (new RepeatingTriggerSchedule($trigger))->findInsideWindow(
            new DateTimeImmutable($now),
            new DateInterval("PT{$windowMinutes}M"),
        );

        self::assertSame($expected, self::format($occurrences));
    }

    public static function occurrencesProvider(): array
    {
        // 2026-08-03 is a Monday, 2026-08-04 a Tuesday, 2026-08-06 a Thursday.
        $daily = self::trigger('daily', '09:00');
        $mondays = self::trigger('weekly', '09:00', 1);
        $fifteenth = self::trigger('monthly', '09:00', 15);

        return [
            // Daily
            'daily_not_due_yet' => [
                $daily, '2026-08-03 08:59:00', 60, [],
            ],
            'daily_exactly_due' => [
                $daily, '2026-08-03 09:00:00', 60, ['2026-08-03 09:00'],
            ],
            'daily_fires_on_a_Monday' => [
                $daily, '2026-08-03 09:05:00', 60, ['2026-08-03 09:00'],
            ],
            'daily_fires_on_a_Tuesday_too' => [
                $daily, '2026-08-04 09:05:00', 60, ['2026-08-04 09:00'],
            ],
            'daily_still_inside_window' => [
                $daily, '2026-08-03 09:59:00', 60, ['2026-08-03 09:00'],
            ],
            'daily_just_outside_window' => [
                $daily, '2026-08-03 10:01:00', 60, [],
            ],
            'daily_window_spans_midnight' => [
                self::trigger('daily', '23:55'), '2026-08-04 00:03:00', 60, ['2026-08-03 23:55'],
            ],

            // Weekly
            'weekly_fires_on_the_picked_day' => [
                $mondays, '2026-08-03 09:05:00', 60, ['2026-08-03 09:00'],
            ],
            'weekly_silent_on_other_days' => [
                $mondays, '2026-08-04 09:05:00', 60, [],
            ],
            'weekly_three_days_old_is_outside_the_window' => [
                $mondays, '2026-08-06 09:05:00', 60, [],
            ],
            'weekly_window_spans_midnight' => [
                self::trigger('weekly', '23:50', 1), '2026-08-04 00:20:00', 60, ['2026-08-03 23:50'],
            ],

            // Monthly
            'monthly_fires_on_the_picked_day' => [$fifteenth, '2026-08-15 09:05:00', 60, ['2026-08-15 09:00']],
            'monthly_silent_on_other_days' => [$fifteenth, '2026-08-16 09:05:00', 60, []],
            'monthly_fires_again_next_month' => [$fifteenth, '2026-09-15 09:05:00', 60, ['2026-09-15 09:00']],

            // A window longer than the schedule's period holds several occurrences, oldest first
            'daily_window_of_three_days' => [
                $daily, '2026-08-05 09:05:00', 3 * 24 * 60, [
                    '2026-08-03 09:00',
                    '2026-08-04 09:00',
                    '2026-08-05 09:00',
                ],
            ],
            // Today's occurrence is not due yet, the three older ones inside the window still are
            'daily_window_ending_before_todays_occurrence' => [
                $daily, '2026-08-05 08:30:00', 3 * 24 * 60, [
                    '2026-08-02 09:00',
                    '2026-08-03 09:00',
                    '2026-08-04 09:00',
                ],
            ],
            // The Monday three weeks back ticked at 09:00, five minutes before the window opens
            'weekly_window_of_three_weeks' => [
                $mondays, '2026-08-17 09:05:00', 21 * 24 * 60, [
                    '2026-08-03 09:00',
                    '2026-08-10 09:00',
                    '2026-08-17 09:00',
                ],
            ],
            'monthly_window_of_two_months' => [
                $fifteenth, '2026-09-15 09:05:00', 62 * 24 * 60, [
                    '2026-08-15 09:00',
                    '2026-09-15 09:00',
                ],
            ],
        ];
    }

    /**
     * A day of month that the current month does not have is clamped to that month's last day, so the
     * trigger fires in every month.
     */
    #[DataProvider('monthEndClampingProvider')]
    public function testMonthEndIsClamped(int $day, string $now, array $expected): void
    {
        $occurrences = (new RepeatingTriggerSchedule(self::trigger('monthly', '09:00', $day)))->findInsideWindow(
            new DateTimeImmutable($now),
            new DateInterval('PT60M'),
        );

        self::assertSame($expected, self::format($occurrences));
    }

    public static function monthEndClampingProvider(): array
    {
        return [
            // February 2026 has 28 days, so days 29-31 all fire on the 28th.
            'day_31_fires_on_28_Feb' => [31, '2026-02-28 09:05:00', ['2026-02-28 09:00']],
            'day_30_fires_on_28_Feb' => [30, '2026-02-28 09:05:00', ['2026-02-28 09:00']],
            'day_29_fires_on_28_Feb' => [29, '2026-02-28 09:05:00', ['2026-02-28 09:00']],
            // ... and not on any other February day.
            'day_31_silent_on_27_Feb' => [31, '2026-02-27 09:05:00', []],
            // 2028 is a leap year, so February has a 29th.
            'day_31_fires_on_29_Feb_in_a_leap_year' => [31, '2028-02-29 09:05:00', ['2028-02-29 09:00']],
            'day_31_silent_on_28_Feb_in_a_leap_year' => [31, '2028-02-28 09:05:00', []],
            // April has 30 days.
            'day_31_fires_on_30_Apr' => [31, '2026-04-30 09:05:00', ['2026-04-30 09:00']],
            // A month that has the day fires on it, unclamped.
            'day_31_fires_on_31_Jan' => [31, '2026-01-31 09:05:00', ['2026-01-31 09:00']],
            'day_31_silent_on_30_Jan' => [31, '2026-01-30 09:05:00', []],
            // A day every month has is never clamped.
            'day_28_fires_on_28_Feb' => [28, '2026-02-28 09:05:00', ['2026-02-28 09:00']],
        ];
    }

    /**
     * The segment plays no part in the schedule, every case uses the same one.
     */
    private static function trigger(string $frequency, string $time, ?int $day = null): RepeatingTrigger
    {
        return RepeatingTrigger::fromValues(
            segmentCode: 'tests_all_users',
            frequency: $frequency,
            time: $time,
            day: $day,
        );
    }

    /**
     * @param DateTimeImmutable[] $occurrences
     * @return string[]
     */
    private static function format(array $occurrences): array
    {
        return array_map(
            static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d H:i'),
            $occurrences,
        );
    }
}
