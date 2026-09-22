<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use RuntimeException;
use Throwable;

class ScenariosVersionsRepository extends Repository
{
    protected $tableName = 'scenarios_versions';

    public function __construct(
        private readonly ScenariosRepository $scenariosRepository,
        Explorer $database,
        ?Storage $cacheStorage = null,
    ) {
        parent::__construct($database, $cacheStorage);
    }

    /**
     * Returns either an inserted version or null if such a version already exists
     */
    final public function add(int $scenarioId, array $payload, ?int $userId = null, ?string $note = null): ?ActiveRow
    {
        $normalized = self::normalize($payload);
        $payloadHash = self::hash($normalized);

        $currentVersion = $this->last($scenarioId);
        if ($currentVersion && $currentVersion->payload_hash === $payloadHash) {
            return null;
        }

        return $this->insert([
            'scenario_id' => $scenarioId,
            'version' => $currentVersion ? $currentVersion->version + 1 : 1,
            'payload' => Json::encode($payload),
            'payload_hash' => $payloadHash,
            'created_at' => new DateTime(),
            'created_by' => $userId,
            'note' => $note,
        ]);
    }


    /**
     * Returns current scenario version (creates initial if missing)
     * @param int $scenarioId
     *
     * @return ActiveRow
     */
    final public function current(int $scenarioId): ActiveRow
    {
        $version = $this->last($scenarioId);
        if ($version) {
            return $version;
        }

        $this->createInitial($scenarioId);
        $version = $this->last($scenarioId);
        if (!$version) {
            throw new RuntimeException("Scenario [{$scenarioId}] has no version and none could be created.");
        }

        return $version;
    }

    final public function createInitial(int $scenarioId): ActiveRow
    {
        $payload = $this->scenariosRepository->getScenario($scenarioId);
        if (!$payload) {
            throw new RuntimeException("scenario [{$scenarioId}] could not be loaded");
        }

        $version = $this->add(scenarioId: $scenarioId, payload: $payload);
        if (!$version) {
            throw new RuntimeException("initial version of scenario [{$scenarioId}] was not stored");
        }

        return $version;
    }

    /**
     * Stores the current definition of every scenario that has no version yet.
     *
     * @return string[] descriptions of scenarios that could not be recorded
     */
    final public function createMissing(): array
    {
        $errors = [];

        $q = $this->scenariosRepository->all(deleted: false)->where(':scenarios_versions.id IS NULL');
        foreach ($q->fetchAll() as $scenario) {
            try {
                $this->createInitial($scenario->id);
            } catch (Throwable $e) {
                // a scenario with broken options can't be loaded, the rest must still be recorded
                $errors[] = "Scenario [{$scenario->id}] {$scenario->name}: {$e->getMessage()}";
            }
        }

        return $errors;
    }

    private function last(int $scenarioId): ?ActiveRow
    {
        return $this->getTable()
            ->where('scenario_id', $scenarioId)
            ->order('version DESC')
            ->limit(1)
            ->fetch();
    }

    private static function hash(array $payload): string
    {
        return hash('sha256', Json::encode($payload));
    }

    private static function normalize(array $payload): array
    {
        // options of elements are decoded as objects, the round trip turns everything into arrays
        $payload = Json::decode(Json::encode($payload), forceArrays: true);
        return self::sortKeys($payload);
    }

    /**
     * Sorts keys of maps recursively.
     */
    private static function sortKeys(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortKeys($item);
            }
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
