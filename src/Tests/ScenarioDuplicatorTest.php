<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Models\Scenario\ScenarioDuplicator;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use stdClass;

class ScenarioDuplicatorTest extends BaseTestCase
{
    private ScenarioDuplicator $scenarioDuplicator;
    private ScenariosRepository $scenariosRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenarioDuplicator = $this->inject(ScenarioDuplicator::class);
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
    }

    public function testDuplicateSimpleScenario(): void
    {
        $originalScenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'Original Scenario',
            'enabled' => true,
            'triggers' => [$this->createTrigger('trigger-uuid-1', ['element-uuid-1'])],
            'elements' => [$this->createEmailElement('element-uuid-1', 'test_template')],
            'visual' => [
                'trigger-uuid-1' => ['x' => 100, 'y' => 100],
                'element-uuid-1' => ['x' => 200, 'y' => 200],
            ],
        ]);

        $duplicatedScenario = $this->scenarioDuplicator->duplicate(
            $originalScenario,
            'Duplicated Scenario',
        );
        $this->assertNotEquals($originalScenario->id, $duplicatedScenario->id);
        $this->assertEquals('Duplicated Scenario', $duplicatedScenario->name);
        $this->assertEquals(0, $duplicatedScenario->enabled);

        $originalRefreshed = $this->scenariosRepository->getScenario($originalScenario->id);
        $this->assertNotFalse($originalRefreshed);
        $this->assertEquals('Original Scenario', $originalRefreshed['name']);
    }

    public function testDuplicateRegeneratesUuids(): void
    {
        $createdScenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'UUID Test Scenario',
            'enabled' => true,
            'triggers' => [
                $this->createTrigger('trigger-uuid-1', ['element-uuid-1', 'element-uuid-2']),
                $this->createTrigger('trigger-uuid-2', ['element-uuid-3']),
            ],
            'elements' => [
                $this->createEmailElement('element-uuid-1', 'test_template_1'),
                $this->createWaitElement('element-uuid-2', 60),
                $this->createEmailElement('element-uuid-3', 'test_template_2'),
            ],
            'visual' => [
                'trigger-uuid-1' => ['x' => 100, 'y' => 100],
                'trigger-uuid-2' => ['x' => 100, 'y' => 300],
                'element-uuid-1' => ['x' => 300, 'y' => 100],
                'element-uuid-2' => ['x' => 500, 'y' => 100],
                'element-uuid-3' => ['x' => 300, 'y' => 300],
            ],
        ]);

        $duplicatedScenario = $this->scenarioDuplicator->duplicate(
            $createdScenario,
            'UUID Duplicated Scenario',
        );

        $originalScenario = $this->scenariosRepository->getScenario($createdScenario->id);
        $duplicatedScenarioData = $this->scenariosRepository->getScenario($duplicatedScenario->id);

        $this->assertNoUuidsMatch($originalScenario, $duplicatedScenarioData);
    }


    public function testDuplicateReplacesNestedUuids(): void
    {
        $createdScenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'Nested UUID Test',
            'enabled' => true,
            'triggers' => [$this->createTrigger('trigger-uuid-1', ['element-uuid-1'])],
            'elements' => [
                $this->createConditionElement('element-uuid-1', 'element-uuid-2', 'element-uuid-3'),
                $this->createEmailElement('element-uuid-2', 'positive_template'),
                $this->createEmailElement('element-uuid-3', 'negative_template'),
            ],
            'visual' => [
                'trigger-uuid-1' => ['x' => 100, 'y' => 100],
                'element-uuid-1' => ['x' => 200, 'y' => 200],
                'element-uuid-2' => ['x' => 300, 'y' => 100],
                'element-uuid-3' => ['x' => 300, 'y' => 300],
            ],
        ]);

        $duplicatedScenario = $this->scenarioDuplicator->duplicate(
            $createdScenario,
            'Nested UUID Duplicate',
        );

        $originalScenario = $this->scenariosRepository->getScenario($createdScenario->id);
        $duplicatedScenarioData = $this->scenariosRepository->getScenario($duplicatedScenario->id);

        // Verify no UUIDs match at top level
        $this->assertNoUuidsMatch($originalScenario, $duplicatedScenarioData);

        // Find condition elements (elements returned as arrays)
        $originalConditionElement = null;
        $duplicatedConditionElement = null;

        foreach ($originalScenario['elements'] as $element) {
            if ($element['type'] === ElementsRepository::ELEMENT_TYPE_CONDITION) {
                $originalConditionElement = $element;
                break;
            }
        }

        foreach ($duplicatedScenarioData['elements'] as $element) {
            if ($element['type'] === ElementsRepository::ELEMENT_TYPE_CONDITION) {
                $duplicatedConditionElement = $element;
                break;
            }
        }

        $this->assertNotNull($originalConditionElement, 'Original condition element not found');
        $this->assertNotNull($duplicatedConditionElement, 'Duplicated condition element not found');

        // Verify nested UUIDs in conditions.nodes[].id are replaced
        // Note: 'conditions' is stdClass from JSON, 'descendants' is array
        $originalNodeId = $originalConditionElement['condition']['conditions']->nodes[0]->id;
        $duplicatedNodeId = $duplicatedConditionElement['condition']['conditions']->nodes[0]->id;

        $this->assertNotEquals($originalNodeId, $duplicatedNodeId, 'Nested node ID should be regenerated');

        // Verify descendants UUIDs are replaced
        $originalDescendants = array_map(
            fn ($d) => $d['uuid'],
            $originalConditionElement['condition']['descendants'],
        );
        $duplicatedDescendants = array_map(
            fn ($d) => $d['uuid'],
            $duplicatedConditionElement['condition']['descendants'],
        );

        foreach ($originalDescendants as $originalUuid) {
            $this->assertNotContains(
                $originalUuid,
                $duplicatedDescendants,
                'Descendant UUIDs must be regenerated',
            );
        }
    }

    public function testDuplicateStripsAbTestSegmentData(): void
    {
        $createdScenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'AB Test Scenario',
            'enabled' => true,
            'triggers' => [$this->createTrigger('trigger-uuid-1', ['element-uuid-1'])],
            'elements' => [$this->createAbTestElement('element-uuid-1', [
                ['code' => 'aaa111', 'name' => 'Variant A', 'distribution' => 50, 'segment_id' => 99, 'segment' => ['id' => 99, 'code' => 'old_segment', 'name' => 'Old Segment']],
                ['code' => 'bbb222', 'name' => 'Variant B', 'distribution' => 50, 'segment_id' => 100, 'segment' => ['id' => 100, 'code' => 'old_segment_2', 'name' => 'Old Segment 2']],
            ])],
            'visual' => [
                'trigger-uuid-1' => ['x' => 100, 'y' => 100],
                'element-uuid-1' => ['x' => 200, 'y' => 200],
            ],
        ]);

        $duplicatedScenario = $this->scenarioDuplicator->duplicate(
            $createdScenario,
            'AB Test Duplicated',
        );

        $duplicatedScenarioData = $this->scenariosRepository->getScenario($duplicatedScenario->id);

        $abTestElement = null;
        foreach ($duplicatedScenarioData['elements'] as $element) {
            if ($element['type'] === ElementsRepository::ELEMENT_TYPE_ABTEST) {
                $abTestElement = $element;
                break;
            }
        }

        $this->assertNotNull($abTestElement, 'Duplicated AB test element not found');

        foreach ($abTestElement[ElementsRepository::ELEMENT_TYPE_ABTEST]['variants'] as $variant) {
            $variant = (array)$variant;
            $this->assertArrayNotHasKey('segment_id', $variant, 'segment_id should be stripped from duplicated variant');
            $this->assertArrayNotHasKey('segment', $variant, 'segment should be stripped from duplicated variant');
            $this->assertNotContains($variant['code'], ['aaa111', 'bbb222'], 'Variant code should be regenerated');
            $this->assertEquals(6, strlen($variant['code']), 'Variant code should be 6 characters');
        }

        // Verify names and distributions are preserved
        $variants = array_map(fn ($v) => (array)$v, $abTestElement[ElementsRepository::ELEMENT_TYPE_ABTEST]['variants']);
        $this->assertEquals('Variant A', $variants[0]['name']);
        $this->assertEquals('Variant B', $variants[1]['name']);
        $this->assertEquals(50, $variants[0]['distribution']);
        $this->assertEquals(50, $variants[1]['distribution']);
    }

    private function createAbTestElement(string $id, array $variants): stdClass
    {
        return self::obj([
            'name' => '',
            'id' => $id,
            'type' => ElementsRepository::ELEMENT_TYPE_ABTEST,
            'ab_test' => [
                'variants' => $variants,
            ],
        ]);
    }

    private function assertNoUuidsMatch(array $original, array $duplicate): void
    {
        $originalTriggerIds = array_map(fn ($t) => $t['id'], $original['triggers']);
        $originalElementIds = array_map(fn ($e) => $e['id'], $original['elements']);
        $originalVisualKeys = array_keys($original['visual']);

        $duplicatedTriggerIds = array_map(fn ($t) => $t['id'], $duplicate['triggers']);
        $duplicatedElementIds = array_map(fn ($e) => $e['id'], $duplicate['elements']);
        $duplicatedVisualKeys = array_keys($duplicate['visual']);

        $this->assertEmpty(
            array_intersect($originalTriggerIds, $duplicatedTriggerIds),
            'No trigger UUIDs should match between original and duplicate',
        );

        $this->assertEmpty(
            array_intersect($originalElementIds, $duplicatedElementIds),
            'No element UUIDs should match between original and duplicate',
        );

        $this->assertEmpty(
            array_intersect($originalVisualKeys, $duplicatedVisualKeys),
            'No visual UUIDs should match between original and duplicate',
        );

        $this->assertCount(count($originalTriggerIds), $duplicatedTriggerIds);
        $this->assertCount(count($originalElementIds), $duplicatedElementIds);
        $this->assertCount(count($originalVisualKeys), $duplicatedVisualKeys);
    }

    private function createTrigger(string $id, array $elementIds): stdClass
    {
        return self::obj([
            'name' => '',
            'id' => $id,
            'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
            'event' => ['code' => 'user_registered'],
            'elements' => $elementIds,
        ]);
    }

    private function createEmailElement(string $id, string $code): stdClass
    {
        return self::obj([
            'name' => '',
            'id' => $id,
            'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
            'email' => ['code' => $code],
        ]);
    }

    private function createWaitElement(string $id, int $minutes): stdClass
    {
        return self::obj([
            'name' => '',
            'id' => $id,
            'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
            'wait' => ['minutes' => $minutes],
        ]);
    }

    private function createConditionElement(string $id, string $positiveUuid, string $negativeUuid): stdClass
    {
        return self::obj([
            'name' => '',
            'id' => $id,
            'type' => ElementsRepository::ELEMENT_TYPE_CONDITION,
            'condition' => [
                'conditions' => [
                    'event' => 'payment',
                    'nodes' => [
                        [
                            'id' => $id,  // This nested ID should be replaced
                            'key' => 'is-recurrent-charge',
                            'params' => [
                                [
                                    'key' => 'is-recurrent-charge',
                                    'values' => [
                                        'selection' => [true],
                                        'operator' => 'or',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'descendants' => [
                    ['uuid' => $positiveUuid, 'direction' => 'positive'],
                    ['uuid' => $negativeUuid, 'direction' => 'negative'],
                ],
            ],
        ]);
    }
}
