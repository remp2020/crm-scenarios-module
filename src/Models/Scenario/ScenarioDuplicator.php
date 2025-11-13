<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Models\Scenario;

use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Database\Table\ActiveRow;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use stdClass;

readonly class ScenarioDuplicator
{
    public function __construct(
        private ScenariosRepository $scenariosRepository,
    ) {
    }

    /**
     * Duplicate a scenario with a new name
     */
    public function duplicate(ActiveRow $scenario, string $newName): ActiveRow
    {
        $originalData = ScenarioData::fromArray(
            scenario: $this->scenariosRepository->getScenario($scenario->id),
        );

        // Regenerate UUIDs for the duplicate (prevents overwriting original scenario)
        $duplicatedData = $this->regenerateUuidsForDuplication($originalData);

        return $this->scenariosRepository->createOrUpdate(
            ScenarioData::fromArray([
                'name' => $newName,
                'triggers' => $duplicatedData->triggers,
                'elements' => $duplicatedData->elements,
                'visual' => $duplicatedData->visual,
                'enabled' => false,
            ])->toArray(),
        );
    }

    /**
     * Regenerate UUIDs for scenario duplication to prevent overwriting original
     */
    private function regenerateUuidsForDuplication(ScenarioData $data): ScenarioData
    {
        // Build complete UUID mapping (old UUID => new UUID) for all triggers and elements
        $uuidMap = [];

        // Generate new UUIDs for all elements
        foreach ($data->elements as $element) {
            $uuidMap[$element['id']] = Uuid::uuid4()->toString();
        }

        // Generate new UUIDs for all triggers
        foreach ($data->triggers as $trigger) {
            $uuidMap[$trigger['id']] = Uuid::uuid4()->toString();
        }

        // Update triggers with new UUIDs and fix element references
        $newTriggers = [];
        foreach ($data->triggers as $trigger) {
            $trigger['id'] = $uuidMap[$trigger['id']];

            // Update element references to point to new element UUIDs
            if (isset($trigger['elements'])) {
                $updatedElements = [];
                foreach ($trigger['elements'] as $oldUuid) {
                    if (!isset($uuidMap[$oldUuid])) {
                        throw new RuntimeException("Trigger references element UUID [{$oldUuid}] not found in scenario");
                    }
                    $updatedElements[] = $uuidMap[$oldUuid];
                }
                $trigger['elements'] = $updatedElements;
            }

            $newTriggers[] = $trigger;
        }

        // Update elements with new UUIDs and fix descendant references
        $newElements = [];
        foreach ($data->elements as $element) {
            $element['id'] = $uuidMap[$element['id']];

            // Recursively replace ALL UUIDs in element type-specific data
            $elementType = $element['type'];
            if (isset($element[$elementType])) {
                $element[$elementType] = $this->replaceUuidsRecursively($element[$elementType], $uuidMap);
            }

            $newElements[] = $element;
        }

        // Update visual positioning data with new UUIDs
        $newVisual = [];
        foreach ($data->visual as $oldUuid => $position) {
            if (!isset($uuidMap[$oldUuid])) {
                throw new RuntimeException("Visual data references UUID [{$oldUuid}] not found in scenario");
            }
            $newVisual[$uuidMap[$oldUuid]] = $position;
        }

        return new ScenarioData(
            name: $data->name,
            triggers: $newTriggers,
            elements: $newElements,
            visual: $newVisual,
            enabled: $data->enabled,
        );
    }

    /**
     * Recursively replace all UUIDs in array/object structure
     */
    private function replaceUuidsRecursively(array $data, array $uuidMap): array
    {
        foreach ($data as $key => &$value) {
            // Recursively process nested arrays
            if (is_array($value)) {
                $value = $this->replaceUuidsRecursively($value, $uuidMap);
                continue;
            }

            // Recursively process stdClass objects (from JSON decode)
            if ($value instanceof stdClass) {
                $value = $this->replaceUuidsInObject($value, $uuidMap);
                continue;
            }

            // Only process string values from here
            if (!is_string($value)) {
                continue;
            }

            // Special handling for 'uuid' keys - these MUST exist in the map
            if ($key === 'uuid') {
                if (!isset($uuidMap[$value])) {
                    throw new RuntimeException("UUID reference [{$value}] not found in scenario");
                }
                $value = $uuidMap[$value];
                continue;
            }

            // Replace UUID string if it exists in our map
            if (isset($uuidMap[$value])) {
                $value = $uuidMap[$value];
            }
        }

        return $data;
    }

    /**
     * Recursively replace UUIDs in stdClass objects
     */
    private function replaceUuidsInObject(stdClass $object, array $uuidMap): stdClass
    {
        foreach (get_object_vars($object) as $key => $value) {
            // Recursively process nested arrays
            if (is_array($value)) {
                $object->$key = $this->replaceUuidsRecursively($value, $uuidMap);
                continue;
            }

            // Recursively process nested objects
            if ($value instanceof stdClass) {
                $object->$key = $this->replaceUuidsInObject($value, $uuidMap);
                continue;
            }

            // Only process string values from here
            if (!is_string($value)) {
                continue;
            }

            // Replace UUID string if it exists in our map
            if (isset($uuidMap[$value])) {
                $object->$key = $uuidMap[$value];
            }
        }

        return $object;
    }
}
