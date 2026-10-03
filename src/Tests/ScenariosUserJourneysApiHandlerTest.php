<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ScenariosModule\Api\ScenariosUserJourneysApiHandler;
use Crm\ScenariosModule\Repositories\JobHistoryRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ScenariosUserJourneysApiHandlerTest extends BaseTestCase
{
    use ApiTestTrait;
    use JobHistoryFixturesTrait;

    private ScenariosUserJourneysApiHandler $handler;
    private ActiveRow $scenario;
    private ActiveRow $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(ScenariosUserJourneysApiHandler::class);
        $this->scenario = $this->createHistoryScenario();
        $this->user = $this->inject(UserManager::class)->addNewUser('user@example.com', false, 'unknown', null, false);
    }

    public function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testUnknownScenario(): void
    {
        $response = $this->callApi(['id' => 999999, 'user_id' => $this->user->id]);
        $payload = $response->getPayload();
        $this->assertSame(404, $response->getCode());
        $this->assertSame('scenario_not_found', $payload['code']);
    }

    public function testMissingUserId(): void
    {
        $response = $this->callApi(['id' => $this->scenario->id]);

        $this->assertSame(400, $response->getCode());
        $this->assertSame('invalid_input', $response->getPayload()['code']);
    }

    public function testJourneysAreListedNewestFirst(): void
    {
        $this->addHistory($this->scenario, $this->user->id, 1, '2026-09-01 10:00:00', '2026-09-01 10:00:01');
        $this->addHistory($this->scenario, $this->user->id, 1, '2026-09-01 10:05:00', '2026-09-03 10:05:00');
        // job without finished_at, its created_at counts as the end
        $this->addHistory($this->scenario, $this->user->id, 2, '2026-09-05 10:00:00');

        $payload = $this->fetchPayload();

        $this->assertFalse($payload['has_more']);
        $this->assertSame([
            [
                'journey_id' => 2,
                'jobs_count' => 1,
                'started_at' => $this->rfc3339('2026-09-05 10:00:00'),
                'ended_at' => $this->rfc3339('2026-09-05 10:00:00'),
            ],
            [
                'journey_id' => 1,
                'jobs_count' => 2,
                'started_at' => $this->rfc3339('2026-09-01 10:00:00'),
                'ended_at' => $this->rfc3339('2026-09-03 10:05:00'),
            ],
        ], $payload['journeys']);
    }

    public function testOtherUsersAndScenariosAreExcluded(): void
    {
        $otherScenario = $this->createHistoryScenario('_other');
        $otherUser = $this->inject(UserManager::class)->addNewUser('other@example.com', false, 'unknown', null, false);

        $this->addHistory($this->scenario, $this->user->id, 1, '2026-09-01 10:00:00');
        $this->addHistory($this->scenario, $otherUser->id, 2, '2026-09-01 10:00:00');
        $this->addHistory($otherScenario, $this->user->id, 3, '2026-09-01 10:00:00');

        $this->assertSame([1], array_column($this->fetchPayload()['journeys'], 'journey_id'));
    }

    public function testUserWithoutJourneys(): void
    {
        $this->assertSame(['journeys' => [], 'has_more' => false], $this->fetchPayload());
    }

    public function testHasMore(): void
    {
        $this->addJourneys(JobHistoryRepository::JOURNEYS_LIMIT);

        $payload = $this->fetchPayload();
        $this->assertCount(JobHistoryRepository::JOURNEYS_LIMIT, $payload['journeys']);
        $this->assertFalse($payload['has_more']);

        $this->addJourneys(1, firstJourneyId: JobHistoryRepository::JOURNEYS_LIMIT + 1);

        $payload = $this->fetchPayload();
        $this->assertCount(JobHistoryRepository::JOURNEYS_LIMIT, $payload['journeys']);
        $this->assertTrue($payload['has_more']);
    }

    private function addJourneys(int $count, int $firstJourneyId = 1): void
    {
        for ($journeyId = $firstJourneyId; $journeyId < $firstJourneyId + $count; $journeyId++) {
            $createdAt = DateTime::from('2026-09-01 10:00:00')->modify("+{$journeyId} minutes");
            $this->addHistory($this->scenario, $this->user->id, $journeyId, $createdAt->format('Y-m-d H:i:s'));
        }
    }

    private function rfc3339(string $date): string
    {
        return DateTime::from($date)->format(DATE_RFC3339);
    }

    private function fetchPayload(): array
    {
        $response = $this->callApi(['id' => $this->scenario->id, 'user_id' => $this->user->id]);
        $this->assertSame(200, $response->getCode());

        return $response->getPayload();
    }

    private function callApi(array $params): JsonApiResponse
    {
        $_GET = array_map(fn ($value) => (string) $value, $params);
        return $this->runJsonApi($this->handler);
    }
}
