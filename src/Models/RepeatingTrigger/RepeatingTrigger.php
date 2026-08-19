<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Models\RepeatingTrigger;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Configuration of a repeating trigger, stored in the `options` column of its `scenarios_triggers` row.
 */
final readonly class RepeatingTrigger
{
    /**
     * Repeating triggers are not bound to any event, the `event_code` column holds this placeholder and
     * the segment they enroll is stored in the options.
     */
    public const string EVENT_CODE = '__repeating_trigger';

    /**
     * Segments used by repeating triggers have to be based on this table.
     */
    public const string SEGMENT_TABLE = 'users';

    /**
     * Repeating triggers are not bound to any event entity, the only param available to their descendants
     * is the ID of the user enrolled from the configured segment.
     */
    public const array OUTPUT_PARAMS = ['user_id'];

    /**
     * The `time` option is a wall-clock 'HH:MM' time.
     */
    public const string TIME_PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    private function __construct(
        public string $segmentCode,
        public RepeatingTriggerFrequencyEnum $frequency,
        public string $time,
        public ?int $day,
    ) {
    }

    /**
     * The options are validated on save, but the column can still be edited afterwards, so a row that no
     * longer carries a usable configuration is refused here rather than acted upon.
     *
     * @throws RepeatingTriggerInvalidDataException
     */
    public static function fromActiveRow(ActiveRow $triggerRow): self
    {
        $options = self::decodeOptions($triggerRow);

        return self::fromValues(
            segmentCode: $options['segment_code'] ?? null,
            frequency: $options['frequency'] ?? null,
            time: $options['time'] ?? null,
            day: $options['day'] ?? null,
            triggerUuid: $triggerRow->uuid,
        );
    }

    /**
     * The values are accepted as they come out of the options, ie. a `day` stored as a numeric string is
     * still a valid day.
     *
     * @param string|null $triggerUuid Identifies the trigger in the error messages, when known
     *
     * @throws RepeatingTriggerInvalidDataException
     */
    public static function fromValues(
        mixed $segmentCode,
        mixed $frequency,
        mixed $time,
        mixed $day = null,
        ?string $triggerUuid = null,
    ): self {
        if (!is_string($segmentCode)) {
            throw new RepeatingTriggerInvalidDataException(
                sprintf('%s has no segment configured', self::describe($triggerUuid)),
            );
        }

        if ($frequency instanceof RepeatingTriggerFrequencyEnum) {
            $frequency = $frequency->value;
        }
        if (!is_string($frequency)) {
            throw new RepeatingTriggerInvalidDataException(
                sprintf('%s has no frequency configured', self::describe($triggerUuid)),
            );
        }
        $frequencyEnum = RepeatingTriggerFrequencyEnum::tryFrom($frequency);
        if ($frequencyEnum === null) {
            throw new RepeatingTriggerInvalidDataException(sprintf(
                '%s has an unknown frequency [%s], expected one of: %s',
                self::describe($triggerUuid),
                $frequency,
                implode(', ', RepeatingTriggerFrequencyEnum::values()),
            ));
        }

        if (!is_string($time) || !preg_match(self::TIME_PATTERN, $time)) {
            throw new RepeatingTriggerInvalidDataException(sprintf(
                "%s has no valid 'HH:MM' time configured",
                self::describe($triggerUuid),
            ));
        }

        return new self(
            segmentCode: $segmentCode,
            frequency: $frequencyEnum,
            time: $time,
            day: $frequencyEnum->maxDay() === null
                ? null
                : self::day($day, $frequencyEnum, $triggerUuid),
        );
    }

    /**
     * @throws RepeatingTriggerInvalidDataException
     */
    private static function decodeOptions(ActiveRow $triggerRow): array
    {
        if (empty($triggerRow->options)) {
            throw new RepeatingTriggerInvalidDataException(
                sprintf('%s has no schedule configured', self::describe($triggerRow->uuid)),
            );
        }

        try {
            $options = Json::decode($triggerRow->options, forceArrays: true);
        } catch (JsonException $exception) {
            throw new RepeatingTriggerInvalidDataException(
                sprintf(
                    '%s has an invalid schedule: %s',
                    self::describe($triggerRow->uuid),
                    $exception->getMessage(),
                ),
                previous: $exception,
            );
        }

        if (!is_array($options)) {
            throw new RepeatingTriggerInvalidDataException(
                sprintf('%s has a malformed schedule', self::describe($triggerRow->uuid)),
            );
        }

        return $options;
    }

    /**
     * @throws RepeatingTriggerInvalidDataException
     */
    private static function day(mixed $day, RepeatingTriggerFrequencyEnum $frequency, ?string $triggerUuid): int
    {
        $validatedDay = filter_var($day, FILTER_VALIDATE_INT);
        if ($validatedDay === false) {
            throw new RepeatingTriggerInvalidDataException(sprintf(
                '%s has no day configured for its [%s] frequency',
                self::describe($triggerUuid),
                $frequency->value,
            ));
        }

        $maxDay = $frequency->maxDay();
        if ($validatedDay < 1 || $validatedDay > $maxDay) {
            throw new RepeatingTriggerInvalidDataException(sprintf(
                'Day [%d] of the [%s] %s is out of range, expected 1-%d',
                $validatedDay,
                $frequency->value,
                lcfirst(self::describe($triggerUuid)),
                $maxDay,
            ));
        }

        return $validatedDay;
    }

    private static function describe(?string $triggerUuid): string
    {
        return $triggerUuid === null
            ? 'Repeating trigger'
            : sprintf('Repeating trigger [%s]', $triggerUuid);
    }
}
