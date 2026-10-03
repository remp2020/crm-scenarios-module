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

/**
 * Lists users who passed through the given scenario,
 * optionally filtered by email or user ID.
 */
class ScenariosUsersApiHandler extends ApiHandler
{
    private const int DEFAULT_LIMIT = 30;

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
            new GetInputParam('query'),
            (new GetInputParam('limit'))->setDefault(self::DEFAULT_LIMIT),
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

        $rows = $this->jobHistoryRepository->usersForScenario(
            scenarioId: $scenarioRow->id,
            emailPrefixOrUserId: $params['query'] ?? null,
            limit: (int) $params['limit'],
        );

        $users = [];
        foreach ($rows as $row) {
            $users[] = [
                'id' => (int)$row->user_id,
                'email' => $row->email,
                'journeys_count' => (int)$row->journeys_count,
                'last_job_at' => $this->formatDate($row->last_job_at),
            ];
        }

        return new JsonApiResponse(Response::S200_OK, ['users' => $users]);
    }

    private function formatDate($value): ?string
    {
        return $value === null ? null : DateTime::from($value)->format(DATE_RFC3339);
    }
}
