<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\ScenariosModule\Api\ScenariosUsersApiHandler;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ScenariosUsersApiHandlerTest extends BaseTestCase
{
    use ApiTestTrait;
    use JobHistoryFixturesTrait;

    private ScenariosUsersApiHandler $handler;
    private ActiveRow $scenario;

    public function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->inject(ScenariosUsersApiHandler::class);
        $this->scenario = $this->createHistoryScenario();
    }

    public function tearDown(): void
    {
        $_GET = [];
        parent::tearDown();
    }

    public function testUnknownScenario(): void
    {
        $response = $this->callApi(['id' => 999999]);

        $this->assertSame(404, $response->getCode());
        $this->assertSame('scenario_not_found', $response->getPayload()['code']);
    }

    public function testMissingScenarioId(): void
    {
        $response = $this->callApi([]);

        $this->assertSame(400, $response->getCode());
        $this->assertSame('invalid_input', $response->getPayload()['code']);
    }

    public function testRecentUsersWithoutQuery(): void
    {
        $older = $this->addUser('older@example.com');
        $newer = $this->addUser('newer@example.com');

        // two jobs of one journey + one more journey
        $this->addHistory($this->scenario, $older->id, 1, '2026-09-01 10:00:00');
        $this->addHistory($this->scenario, $older->id, 1, '2026-09-01 10:05:00');
        $this->addHistory($this->scenario, $older->id, 2, '2026-09-02 10:00:00');
        $this->addHistory($this->scenario, $newer->id, 3, '2026-09-03 10:00:00');

        $users = $this->fetchUsers();

        $this->assertSame([$newer->id, $older->id], array_column($users, 'id'));
        $this->assertSame('older@example.com', $users[1]['email']);
        // counts journeys, not jobs
        $this->assertSame(2, $users[1]['journeys_count']);
        $this->assertSame((new \DateTime('2026-09-02 10:00:00'))->format(DATE_RFC3339), $users[1]['last_job_at']);
    }

    public function testNumericQueryMatchesUserIdExactly(): void
    {
        $user = $this->addUser('user@example.com');
        // numeric query must not fall back to email prefix search
        $digitsEmailUser = $this->addUser("{$user->id}abc@example.com");

        $this->addHistory($this->scenario, $user->id, 1, '2026-09-01 10:00:00');
        $this->addHistory($this->scenario, $digitsEmailUser->id, 2, '2026-09-01 10:00:00');

        $this->assertSame([$user->id], array_column($this->fetchUsers((string) $user->id), 'id'));
    }

    public function testEmailPrefixQuery(): void
    {
        $match = $this->addUser('ab@example.com');
        $other = $this->addUser('other@example.com');

        $this->addHistory($this->scenario, $match->id, 1, '2026-09-01 10:00:00');
        $this->addHistory($this->scenario, $other->id, 2, '2026-09-01 10:00:00');

        $this->assertSame([$match->id], array_column($this->fetchUsers('ab'), 'id'));
        // case-insensitive collation
        $this->assertSame([$match->id], array_column($this->fetchUsers('AB'), 'id'));
    }

    public function testLikeWildcardsAreEscaped(): void
    {
        $user = $this->addUser('axb@example.com');
        $this->addHistory($this->scenario, $user->id, 1, '2026-09-01 10:00:00');

        $this->assertSame([], $this->fetchUsers('a_'));
        $this->assertSame([], $this->fetchUsers('%'));
    }

    public function testOtherScenarioAndJobsWithoutJourneyAreExcluded(): void
    {
        $otherScenario = $this->createHistoryScenario('_other');
        $inScenario = $this->addUser('in@example.com');
        $inOtherScenario = $this->addUser('elsewhere@example.com');
        $withoutJourney = $this->addUser('nojourney@example.com');

        $this->addHistory($this->scenario, $inScenario->id, 1, '2026-09-01 10:00:00');
        $this->addHistory($otherScenario, $inOtherScenario->id, 2, '2026-09-01 10:00:00');
        $this->addHistory($this->scenario, $withoutJourney->id, null, '2026-09-01 10:00:00');

        $this->assertSame([$inScenario->id], array_column($this->fetchUsers(), 'id'));
    }

    public function testLimit(): void
    {
        foreach ([1 => 'a', 2 => 'b', 3 => 'c'] as $i => $name) {
            $user = $this->addUser("{$name}@example.com");
            $this->addHistory($this->scenario, $user->id, $i, "2026-09-0{$i} 10:00:00");
        }

        $this->assertCount(1, $this->fetchUsers(limit: 1));
        // clamped to at least one
        $this->assertCount(1, $this->fetchUsers(limit: 0));
        $this->assertCount(3, $this->fetchUsers(limit: 30));
        // default limit is used when the parameter is not sent
        $this->assertCount(3, $this->fetchUsers());
    }

    public function testHistoryOfNonExistingUserIsSkipped(): void
    {
        $user = $this->addUser('user@example.com');

        $this->addHistory($this->scenario, $user->id, 1, '2026-09-01 10:00:00');
        // no foreign key on user_id, history can outlive the user
        $this->addHistory($this->scenario, 999999, 2, '2026-09-02 10:00:00');

        $this->assertSame([$user->id], array_column($this->fetchUsers(), 'id'));
    }

    private function addUser(string $email): ActiveRow
    {
        return $this->inject(UserManager::class)->addNewUser($email, false, 'unknown', null, false);
    }

    private function fetchUsers(?string $query = null, ?int $limit = null): array
    {
        $params = array_filter(
            ['id' => $this->scenario->id, 'query' => $query, 'limit' => $limit],
            fn ($value) => $value !== null,
        );
        $response = $this->callApi($params);
        $this->assertSame(200, $response->getCode());

        return $response->getPayload()['users'];
    }

    private function callApi(array $params): JsonApiResponse
    {
        $_GET = array_map(fn ($value) => (string) $value, $params);
        return $this->runJsonApi($this->handler);
    }
}
