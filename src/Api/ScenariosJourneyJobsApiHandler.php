<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobHistoryRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ScenariosJourneyJobsApiHandler extends ApiHandler
{
    public function __construct(
        private readonly ScenariosRepository $scenariosRepository,
        private readonly JobHistoryRepository $jobHistoryRepository,
        private readonly JobsRepository $jobsRepository,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired(),
            (new GetInputParam('journey_id'))->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $scenarioRow = $this->scenariosRepository->find((int)$params['id']);

        if (!$scenarioRow) {
            return new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'code' => 'scenario_not_found',
                'message' => "Scenario with ID [{$params['id']}] not found.",
            ]);
        }

        $journeyId = (int)$params['journey_id'];

        $triggers = $elements = [];
        foreach ($scenarioRow->related('scenarios_triggers') as $node) {
            $triggers[$node->id] = $node;
        }
        foreach ($scenarioRow->related('scenarios_elements') as $node) {
            $elements[$node->id] = $node;
        }

        $rows = [];
        foreach ($this->jobHistoryRepository->jobsForJourney($scenarioRow->id, $journeyId) as $row) {
            $rows[(int)$row->job_id] = [
                'job_id' => (int)$row->job_id,
                'created_at' => $row->job_created_at,
                'in_progress' => false,
                'row' => $row,
            ];
        }
        // jobs get into history only once processed, running ones (e.g. waits) are only in the jobs table
        $q = $this->jobsRepository->getAllJobs()->where('journey_id', $journeyId);
        foreach ($q as $row) {
            $node = $row->trigger_id ? ($triggers[$row->trigger_id] ?? null) : ($elements[$row->element_id] ?? null);
            // history wins, the job may have been processed in the meantime
            if ($node === null || isset($rows[$row->id])) {
                continue;
            }
            $rows[$row->id] = [
                'job_id' => $row->id,
                'created_at' => $row->created_at,
                'in_progress' => true,
                'row' => $row,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['created_at'], $a['job_id']] <=> [$b['created_at'], $b['job_id']]);

        $jobs = [];
        foreach ($rows as ['job_id' => $jobId, 'created_at' => $createdAt, 'in_progress' => $inProgress, 'row' => $row]) {
            if ($row->trigger_id) {
                $nodeKind = 'trigger';
                $node = $triggers[(int)$row->trigger_id];
            } else {
                $nodeKind = 'element';
                $node = $elements[(int)$row->element_id];
            }

            $jobs[] = [
                'job_id' => $jobId,
                'parent_job_id' => $row->parent_job_id === null ? null : (int)$row->parent_job_id,
                'node' => [
                    'kind' => $nodeKind,
                    'id' => $node->id,
                    'name' => $node->name ?? ucfirst(str_replace('_', ' ', $node->type)) . " #{$node->id}",
                    'type' => $node->type,
                    'uuid' => $node->uuid,
                    'deleted' => $node->deleted_at !== null,
                ],
                'state' => $row->state,
                'in_progress' => $inProgress,
                'wait_until' => $inProgress ? $this->formatDate($this->waitUntil($nodeKind, $node, $row)) : null,
                'retry_count' => (int)$row->retry_count,
                'created_at' => $this->formatDate($createdAt),
                'started_at' => $this->formatDate($row->started_at),
                'finished_at' => $this->formatDate($row->finished_at),
                'parameters' => $this->decodeJson($row->parameters),
                'result' => $this->decodeJson($row->result),
                'context' => $this->decodeJson($row->context),
            ];
        }

        return new JsonApiResponse(Response::S200_OK, ['jobs' => $jobs]);
    }

    /**
     * The engine doesn't store when a wait ends, it's its start + configured minutes.
     */
    private function waitUntil(string $nodeKind, ActiveRow $node, ActiveRow $job): ?\DateTimeInterface
    {
        if ($nodeKind !== 'element' || $node->type !== ElementsRepository::ELEMENT_TYPE_WAIT || $job->started_at === null) {
            return null;
        }

        $options = $this->decodeJson($node->options);
        $minutes = is_array($options) ? ($options['minutes'] ?? null) : null;
        if (!is_numeric($minutes)) {
            return null;
        }

        return DateTime::from($job->started_at)->modify('+' . (int)$minutes . ' minutes');
    }

    private function decodeJson(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Json::decode($value, forceArrays: true);
        } catch (JsonException) {
            // don't hide a job because its payload can't be parsed
            // show it raw instead
            return $value;
        }
    }

    private function formatDate($value): ?string
    {
        return $value === null ? null : DateTime::from($value)->format(DATE_RFC3339);
    }
}
