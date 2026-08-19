<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Models\RepeatingTrigger;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum RepeatingTriggerFrequencyEnum: string
{
    use EnumHelper;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * The highest day the frequency accepts, NULL when it is not configured with a day at all.
     */
    public function maxDay(): ?int
    {
        return match ($this) {
            self::Daily => null,
            self::Weekly => 7,
            self::Monthly => 31,
        };
    }
}
