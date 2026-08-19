<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerFrequencyEnum;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerInvalidDataException;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepeatingTriggerOptionsTest extends TestCase
{
    public function testReadsTheScheduleFromOptions(): void
    {
        $trigger = RepeatingTrigger::fromActiveRow($this->triggerRow([
            'segment_code' => 'tests_all_users',
            'frequency' => 'weekly',
            'day' => 1,
            'time' => '09:00',
        ]));

        $this->assertSame('tests_all_users', $trigger->segmentCode);
        $this->assertSame(RepeatingTriggerFrequencyEnum::Weekly, $trigger->frequency);
        $this->assertSame('09:00', $trigger->time);
        $this->assertSame(1, $trigger->day);
    }

    public function testDailyScheduleHasNoDay(): void
    {
        // The day is meaningless for a daily schedule, even a stored one is dropped.
        $trigger = RepeatingTrigger::fromActiveRow($this->triggerRow([
            'segment_code' => 'tests_all_users',
            'frequency' => 'daily',
            'day' => 1,
            'time' => '09:00',
        ]));

        $this->assertSame(RepeatingTriggerFrequencyEnum::Daily, $trigger->frequency);
        $this->assertNull($trigger->day);
    }

    public function testMonthlyDayIsKept(): void
    {
        $trigger = RepeatingTrigger::fromActiveRow($this->triggerRow([
            'segment_code' => 'tests_all_users',
            'frequency' => 'monthly',
            'day' => 31,
            'time' => '23:59',
        ]));

        $this->assertSame(31, $trigger->day);
        $this->assertSame('23:59', $trigger->time);
    }

    public function testValidatesPlainValues(): void
    {
        // A day kept as a numeric string is what the options of a hand-edited row can hold.
        $trigger = RepeatingTrigger::fromValues(
            segmentCode: 'tests_all_users',
            frequency: 'weekly',
            time: '09:00',
            day: '2',
        );

        $this->assertSame('tests_all_users', $trigger->segmentCode);
        $this->assertSame(2, $trigger->day);
    }

    public function testRefusesPlainValuesWithoutIdentifyingATrigger(): void
    {
        $this->expectException(RepeatingTriggerInvalidDataException::class);
        $this->expectExceptionMessage('Repeating trigger has an unknown frequency [yearly]');

        RepeatingTrigger::fromValues(
            segmentCode: 'tests_all_users',
            frequency: 'yearly',
            time: '09:00',
            day: 1,
        );
    }

    /**
     * The options are validated on save, so these rows can only come from a column edited afterwards.
     */
    #[DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRefused(?string $options, string $expectedMessage): void
    {
        $row = new ActiveRow(
            ['id' => 1, 'uuid' => 'trigger_repeating', 'options' => $options],
            self::createStub(Selection::class),
        );

        $this->expectException(RepeatingTriggerInvalidDataException::class);
        $this->expectExceptionMessage($expectedMessage);

        RepeatingTrigger::fromActiveRow($row);
    }

    public static function invalidOptionsProvider(): array
    {
        $valid = [
            'segment_code' => 'tests_all_users',
            'frequency' => 'weekly',
            'day' => 1,
            'time' => '09:00',
        ];

        return [
            'no options at all' => [null, 'has no schedule configured'],
            'empty options' => ['', 'has no schedule configured'],
            'unparseable json' => ['{"frequency": ', 'has an invalid schedule'],
            'json scalar' => ['"weekly"', 'has a malformed schedule'],

            'missing segment' => [self::options($valid, ['segment_code' => null]), 'has no segment configured'],
            'segment not a string' => [self::options($valid, ['segment_code' => 42]), 'has no segment configured'],

            'missing frequency' => [self::options($valid, ['frequency' => null]), 'has no frequency configured'],
            'unknown frequency' => [self::options($valid, ['frequency' => 'yearly']), 'has an unknown frequency [yearly]'],

            'missing time' => [self::options($valid, ['time' => null]), "has no valid 'HH:MM' time configured"],
            'time without minutes' => [self::options($valid, ['time' => '09']), "has no valid 'HH:MM' time configured"],
            'time out of range' => [self::options($valid, ['time' => '24:00']), "has no valid 'HH:MM' time configured"],

            'weekly without a day' => [
                self::options($valid, ['day' => null]),
                'has no day configured for its [weekly] frequency',
            ],
            'day not a number' => [
                self::options($valid, ['day' => 'monday']),
                'has no day configured for its [weekly] frequency',
            ],
            'weekly day out of range' => [
                self::options($valid, ['day' => 8]),
                'is out of range, expected 1-7',
            ],
            'monthly day out of range' => [
                self::options($valid, ['frequency' => 'monthly', 'day' => 32]),
                'is out of range, expected 1-31',
            ],
        ];
    }

    private function triggerRow(array $options): ActiveRow
    {
        return new ActiveRow(
            ['id' => 1, 'uuid' => 'trigger_repeating', 'options' => Json::encode($options)],
            self::createStub(Selection::class),
        );
    }

    /**
     * Valid options with the given changes applied; a NULL value removes the option.
     */
    private static function options(array $options, array $changes): string
    {
        foreach ($changes as $option => $value) {
            if ($value === null) {
                unset($options[$option]);
                continue;
            }

            $options[$option] = $value;
        }

        return Json::encode($options);
    }
}
