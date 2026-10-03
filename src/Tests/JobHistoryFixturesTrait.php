<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobHistoryRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

/**
 * History rows are inserted directly (not by running the engine),
 * so tests have full control over users, journeys and timestamps.
 */
trait JobHistoryFixturesTrait
{
    private int $nextJobId = 1;

    private function createHistoryScenario(string $suffix = ''): ActiveRow
    {
        return $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => "history test {$suffix}",
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => "trigger{$suffix}",
                    'event' => ['code' => 'user_registered'],
                    'elements' => ["element_wait{$suffix}"],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => "element_wait{$suffix}",
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => 10],
                ]),
            ],
        ]);
    }

    private function addHistory(
        ActiveRow $scenario,
        int $userId,
        ?int $journeyId,
        string $jobCreatedAt,
        ?string $finishedAt = null,
    ): void {
        $trigger = $scenario->related('scenarios_triggers')->fetch();

        $this->getRepository(JobHistoryRepository::class)->insert([
            'created_at' => DateTime::from($finishedAt ?? $jobCreatedAt),
            'job_id' => $this->nextJobId++,
            'scenario_id' => $scenario->id,
            'trigger_id' => $trigger->id,
            'journey_id' => $journeyId,
            'user_id' => $userId,
            'state' => JobsRepository::STATE_FINISHED,
            'retry_count' => 0,
            'job_created_at' => DateTime::from($jobCreatedAt),
            'started_at' => DateTime::from($jobCreatedAt),
            'finished_at' => $finishedAt === null ? null : DateTime::from($finishedAt),
        ]);
    }
}
