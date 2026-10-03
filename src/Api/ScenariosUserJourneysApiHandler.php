<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ScenariosModule\Repositories\JobHistoryRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ScenariosUserJourneysApiHandler extends ApiHandler
{
    public function __construct(
        private readonly ScenariosRepository $scenariosRepository,
        private readonly JobHistoryRepository $jobHistoryRepository,
    ) {
        parent::__construct();
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired(),
            (new GetInputParam('user_id'))->setRequired(),
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

        $rows = $this->jobHistoryRepository->journeysForUser($scenarioRow->id, (int)$params['user_id']);
        $hasMore = count($rows) > JobHistoryRepository::JOURNEYS_LIMIT;
        $rows = array_slice($rows, 0, JobHistoryRepository::JOURNEYS_LIMIT);

        $journeys = [];
        foreach ($rows as $row) {
            $journeys[] = [
                'journey_id' => (int)$row->journey_id,
                'jobs_count' => (int)$row->jobs_count,
                'started_at' => $this->formatDate($row->started_at),
                'ended_at' => $this->formatDate($row->ended_at),
            ];
        }

        return new JsonApiResponse(Response::S200_OK, [
            'journeys' => $journeys,
            'has_more' => $hasMore,
        ]);
    }

    private function formatDate($value): ?string
    {
        return $value === null ? null : DateTime::from($value)->format(DATE_RFC3339);
    }
}
