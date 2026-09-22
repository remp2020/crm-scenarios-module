<?php
declare(strict_types=1);

use Crm\ScenariosModule\Repositories\ScenariosVersionsRepository;
use Nette\Database\Explorer;
use Phinx\Migration\AbstractMigration;

final class CreateInitialScenarioVersions extends AbstractMigration
{
    public function up(): void
    {
        $app = $GLOBALS['application'] ?? null;
        if (!$app) {
            throw new \Exception("Unable to load application from \$GLOBALS['application'] variable, cannot create initial scenario versions.");
        }

        $container = $app->getContainer();

        $container->getByType(Explorer::class)->getStructure()->rebuild();

        /** @var ScenariosVersionsRepository $scenariosVersionsRepository */
        $scenariosVersionsRepository = $container->getByType(ScenariosVersionsRepository::class);

        foreach ($scenariosVersionsRepository->createMissing() as $error) {
            // a broken scenario must not stop the migration, it gets its first version on the next save
            $this->getOutput()?->writeln("<comment>Skipped: {$error}</comment>");
        }
    }

    public function down(): void
    {
        // versions are dropped together with the scenarios_versions table
    }
}
