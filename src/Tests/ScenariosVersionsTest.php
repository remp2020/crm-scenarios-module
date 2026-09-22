<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\ScenariosVersionsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Nette\Database\Table\ActiveRow;
use RuntimeException;

class ScenariosVersionsTest extends BaseTestCase
{
    private ScenariosRepository $scenariosRepository;
    private ScenariosVersionsRepository $scenariosVersionsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->scenariosVersionsRepository = $this->getRepository(ScenariosVersionsRepository::class);
    }

    public function testSavingAScenarioStoresItsDefinition(): void
    {
        $scenario = $this->saveScenario();

        $versions = $this->versionsOf($scenario->id);
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]->version);

        // the payload is the same structure createOrUpdate() consumes, referenced by uuid
        $payload = json_decode($versions[0]->payload, true);
        $this->assertSame('versioned', $payload['name']);
        $this->assertSame(['element_wait'], array_column($payload['elements'], 'id'));
        $this->assertSame(['trigger_user_registered'], array_column($payload['triggers'], 'id'));
    }

    public function testSavingWithoutAnyChangeStoresNoVersion(): void
    {
        $scenario = $this->saveScenario();
        $this->saveScenario($scenario->id);

        $this->assertCount(1, $this->versionsOf($scenario->id));
    }

    public function testChangingElementOptionsStoresANewVersion(): void
    {
        $scenario = $this->saveScenario();
        $this->saveScenario($scenario->id, minutes: 20);

        $versions = $this->versionsOf($scenario->id);
        $this->assertCount(2, $versions);
        $this->assertSame(2, $versions[1]->version);
        $this->assertNotSame($versions[0]->payload_hash, $versions[1]->payload_hash);
    }

    public function testRenamingStoresANewVersion(): void
    {
        $scenario = $this->saveScenario();
        $this->saveScenario($scenario->id, elementName: 'Wait a bit');

        $versions = $this->versionsOf($scenario->id);
        $this->assertCount(2, $versions);
        $this->assertNotSame($versions[0]->payload_hash, $versions[1]->payload_hash);
    }

    public function testMovingNodesOnTheCanvasStoresANewVersion(): void
    {
        $scenario = $this->saveScenario();
        $this->saveScenario($scenario->id, visual: ['element_wait' => ['x' => 100, 'y' => 250]]);

        // the visual layout is part of the stored definition, so moving a node is a change like any other
        $versions = $this->versionsOf($scenario->id);
        $this->assertCount(2, $versions);
        $this->assertNotSame($versions[0]->payload_hash, $versions[1]->payload_hash);
    }

    public function testHashDoesNotDependOnTheOrderOfNodes(): void
    {
        $scenario = $this->saveScenario();
        $this->scenariosVersionsRepository->getTable()->where('scenario_id', $scenario->id)->delete();

        // getScenario() does not order elements and triggers, so the same definition can come
        // back in either order - that must not look like a change
        $payload = [
            'id' => $scenario->id,
            'name' => 'versioned',
            'triggers' => [],
            // getScenario() keys elements and triggers by uuid, so this is a map, not a list
            'elements' => [
                'element_a' => ['id' => 'element_a', 'name' => 'A', 'wait' => ['minutes' => 10]],
                'element_b' => ['id' => 'element_b', 'name' => 'B', 'wait' => ['minutes' => 20]],
            ],
            'visual' => [],
        ];
        $reordered = $payload;
        $reordered['elements'] = array_reverse($payload['elements'], preserve_keys: true);

        $this->assertNotNull($this->scenariosVersionsRepository->add($scenario->id, $payload));
        $this->assertNull($this->scenariosVersionsRepository->add($scenario->id, $reordered));
    }

    public function testCurrentCreatesTheInitialVersionOfAScenarioWithoutOne(): void
    {
        $scenario = $this->saveScenario();
        // a scenario that predates versioning has no version row
        $this->scenariosVersionsRepository->getTable()->where('scenario_id', $scenario->id)->delete();

        $current = $this->scenariosVersionsRepository->current($scenario->id);

        $this->assertSame(1, $current->version);
        $this->assertCount(1, $this->versionsOf($scenario->id));

        // the definition is recorded once, further reads return the same row
        $this->assertSame($current->id, $this->scenariosVersionsRepository->current($scenario->id)->id);
        $this->assertCount(1, $this->versionsOf($scenario->id));
    }

    public function testCurrentReturnsTheLatestVersion(): void
    {
        $scenario = $this->saveScenario();
        $this->saveScenario($scenario->id, minutes: 20);

        $this->assertSame(2, $this->scenariosVersionsRepository->current($scenario->id)->version);
    }

    public function testCurrentFailsWhenTheScenarioCannotBeLoaded(): void
    {
        $this->expectException(RuntimeException::class);
        $this->scenariosVersionsRepository->current(999999);
    }

    public function testInitialVersionsCoverScenariosWithoutVersion(): void
    {
        $scenario = $this->saveScenario();
        // a scenario that predates versioning has no version row
        $this->scenariosVersionsRepository->getTable()->where('scenario_id', $scenario->id)->delete();

        $q = $this->scenariosRepository->all(deleted: false)->where(':scenarios_versions.id IS NULL');

        $this->assertSame(
            [$scenario->id],
            array_map(
                fn ($row) => $row->id,
                array_values($q->fetchAll()),
            ),
        );

        $this->assertSame([], $this->createInitialVersions());

        $versions = $this->versionsOf($scenario->id);
        $this->assertCount(1, $versions);
        $this->assertSame(1, $versions[0]->version);

        $q = $this->scenariosRepository->all(deleted: false)->where(':scenarios_versions.id IS NULL');
        $this->assertCount(0, $q->fetchAll());

        // running it again changes nothing
        $this->assertSame([], $this->createInitialVersions());
        $this->assertCount(1, $this->versionsOf($scenario->id));
    }

    /**
     * @return string[] scenarios the backfill could not record
     */
    private function createInitialVersions(): array
    {
        return $this->scenariosVersionsRepository->createMissing();
    }

    /**
     * @return ActiveRow[] versions of the scenario, oldest first
     */
    private function versionsOf(int $scenarioId): array
    {
        return array_values(
            $this->scenariosVersionsRepository->getTable()
                ->where('scenario_id', $scenarioId)
                ->order('version')
                ->fetchAll(),
        );
    }

    private function saveScenario(
        ?int $scenarioId = null,
        int $minutes = 10,
        string $elementName = 'Wait',
        ?array $visual = null,
    ): ActiveRow {
        $data = [
            'name' => 'versioned',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => 'Registered',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger_user_registered',
                    'event' => ['code' => 'user_registered'],
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [
                self::obj([
                    'name' => $elementName,
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => ['minutes' => $minutes],
                ]),
            ],
        ];

        if ($visual !== null) {
            $data['visual'] = $visual;
        }
        if ($scenarioId !== null) {
            $data['id'] = $scenarioId;
        }

        $scenario = $this->scenariosRepository->createOrUpdate($data, null);
        $this->assertInstanceOf(ActiveRow::class, $scenario);

        return $scenario;
    }
}
