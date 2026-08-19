<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerScheduleStateEnum;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class RepeatingTriggerScheduleRepository extends Repository
{
    protected $tableName = 'scenarios_repeating_trigger_schedule';

    final public function claim(int $triggerId, \DateTimeInterface $occurredAt): ActiveRow
    {
        $now = new DateTime();
        return $this->insert([
            'trigger_id' => $triggerId,
            'occurred_at' => $occurredAt,
            'state' => RepeatingTriggerScheduleStateEnum::Running->value,
            'jobs_created' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    final public function recordSkipped(int $triggerId, \DateTimeInterface $occurredAt): ActiveRow
    {
        $now = new DateTime();
        return $this->insert([
            'trigger_id' => $triggerId,
            'occurred_at' => $occurredAt,
            'state' => RepeatingTriggerScheduleStateEnum::Skipped->value,
            'jobs_created' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'finished_at' => $now,
        ]);
    }

    final public function findByTriggerAndOccurrence(int $triggerId, \DateTimeInterface $occurredAt): ?ActiveRow
    {
        return $this->getTable()->where([
            'trigger_id' => $triggerId,
            'occurred_at' => $occurredAt,
        ])->fetch();
    }

    final public function advance(ActiveRow $triggerOccurrence, int $jobsAdded): void
    {
        $this->getTable()
            ->where('id = ?', $triggerOccurrence->id)
            ->update([
                'jobs_created+=' => $jobsAdded,
                'updated_at' => new DateTime(),
            ]);
    }

    final public function finish(ActiveRow &$triggerOccurrence): void
    {
        $now = new DateTime();
        $this->update($triggerOccurrence, [
            'state' => RepeatingTriggerScheduleStateEnum::Finished->value,
            'updated_at' => $now,
            'finished_at' => $now,
        ]);
    }

    final public function fail(ActiveRow &$triggerOccurrence): void
    {
        $now = new DateTime();

        $this->update($triggerOccurrence, [
            'state' => RepeatingTriggerScheduleStateEnum::Failed->value,
            'updated_at' => $now,
            'finished_at' => $now,
        ]);
    }
}
