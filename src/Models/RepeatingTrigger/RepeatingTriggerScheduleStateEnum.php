<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Models\RepeatingTrigger;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum RepeatingTriggerScheduleStateEnum: string
{
    use EnumHelper;

    case Running = 'running';
    case Finished = 'finished';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
