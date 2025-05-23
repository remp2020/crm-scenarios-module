<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\ScenariosModule\Events\ABTestDistributeEventHandler;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\SelectedVariantsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;
use Ramsey\Uuid\Uuid;

class ABTestDistributeEventHandlerTest extends DatabaseTestCase
{
    /** @var JobsRepository */
    private $scenarioJobsRepository;

    /** @var SelectedVariantsRepository */
    private $selectedVariantRepository;

    /** @var ActiveRow */
    private $userRow;

    protected function requiredRepositories(): array
    {
        return [
            JobsRepository::class,
            UsersRepository::class,
            ScenariosRepository::class,
            ElementsRepository::class,
            SelectedVariantsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->scenarioJobsRepository = $this->getRepository(JobsRepository::class);
        $this->selectedVariantRepository = $this->getRepository(SelectedVariantsRepository::class);

        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $this->userRow = $userManager->addNewUser('test@test.sk');
    }

    public function testSelectingDistributionWithValidInputs(): void
    {
        $variants = [
            ['name' => 'variant_1', 'distribution' => 50, 'code' => 'ab3456'],
            ['name' => 'variant_2', 'distribution' => 40, 'code' => '6543ba'],
            ['name' => 'variant_3', 'distribution' => 10, 'code' => '1234ef'],
        ];

        // Prepare element and schedule job
        $scenarioElementRow = $this->prepareScenarioElementWithTrigger($variants);
        $scenarioJobRow = $this->scenarioJobsRepository->addElement($scenarioElementRow->id, ['user_id' => $this->userRow->id]);
        $scenarioJobRow = $this->scenarioJobsRepository->scheduleJob($scenarioJobRow);

        $message = ABTestDistributeEventHandler::createHermesMessage($scenarioJobRow->id);

        /** @var ABTestDistributeEventHandler $ABTestDistributeHandler */
        $ABTestDistributeHandler = $this->inject(ABTestDistributeEventHandler::class);
        $ABTestDistributeHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);
        $selectedVariantRow = $this->selectedVariantRepository->findBy('element_id', $scenarioJobRow->element_id);

        $result = Json::decode($scenarioJobRow->result, Json::FORCE_ARRAY);

        $this->assertArrayHasKey(ABTestDistributeEventHandler::RESULT_PARAM_SELECTED_VARIANT_CODE, $result);
        $this->assertArrayHasKey(ABTestDistributeEventHandler::RESULT_PARAM_SELECTED_VARIANT_INDEX, $result);

        $this->assertContains($result[ABTestDistributeEventHandler::RESULT_PARAM_SELECTED_VARIANT_CODE], array_column($variants, 'code'));
        $this->assertEquals(
            array_search($result[ABTestDistributeEventHandler::RESULT_PARAM_SELECTED_VARIANT_CODE], array_column($variants, 'code')),
            $result[ABTestDistributeEventHandler::RESULT_PARAM_SELECTED_VARIANT_INDEX],
        );

        $this->assertEquals(JobsRepository::STATE_FINISHED, $scenarioJobRow->state);

        $this->assertNotNull($selectedVariantRow);
        $this->assertEquals($result[ABTestDistributeEventHandler::RESULT_PARAM_SELECTED_VARIANT_CODE], $selectedVariantRow->variant_code);
    }

    public function testSelectingDistributionForUserAlreadySelected(): void
    {
        $variants = [
            ['name' => 'variant_1', 'distribution' => 50, 'code' => 'ab3456'],
            ['name' => 'variant_2', 'distribution' => 40, 'code' => '6543ba'],
            ['name' => 'variant_3', 'distribution' => 10, 'code' => '1234ef'],
        ];

        // Prepare element and schedule job
        $scenarioElementRow = $this->prepareScenarioElementWithTrigger($variants);
        $scenarioJobRow = $this->scenarioJobsRepository->addElement($scenarioElementRow->id, ['user_id' => $this->userRow->id]);
        $scenarioJobRow = $this->scenarioJobsRepository->scheduleJob($scenarioJobRow);

        $message = ABTestDistributeEventHandler::createHermesMessage($scenarioJobRow->id);

        /** @var ABTestDistributeEventHandler $ABTestDistributeHandler */
        $ABTestDistributeHandler = $this->inject(ABTestDistributeEventHandler::class);
        $ABTestDistributeHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);
        $firstResult = Json::decode($scenarioJobRow->result, Json::FORCE_ARRAY);

        // schedule new job with same element
        $scenarioJobRow = $this->scenarioJobsRepository->addElement($scenarioElementRow->id, ['user_id' => $this->userRow->id]);
        $scenarioJobRow = $this->scenarioJobsRepository->scheduleJob($scenarioJobRow);

        $message = ABTestDistributeEventHandler::createHermesMessage($scenarioJobRow->id);
        $ABTestDistributeHandler->handle($message);

        $scenarioJobRow = $this->scenarioJobsRepository->find($scenarioJobRow->id);
        $secondResult = Json::decode($scenarioJobRow->result, Json::FORCE_ARRAY);

        $this->assertEquals($firstResult, $secondResult);
    }

    private function prepareScenarioElementWithTrigger(array $variants): ActiveRow
    {
        /** @var ScenariosRepository $scenariosRepository */
        $scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRow = $scenariosRepository->insert([
            'name' => 'Testing scenarios',
            'visual' => '{}',
            'created_at' => new \DateTime(),
            'modified_at' => new \DateTime(),
            'enabled' => 1,
        ]);

        /** @var ElementsRepository $scenarioElementRepository */
        $scenarioElementRepository = $this->getRepository(ElementsRepository::class);
        $scenarioElementRow = $scenarioElementRepository->insert([
            'scenario_id' => $scenarioRow->id,
            'uuid' => Uuid::uuid4(),
            'name' => 'test element name',
            'type' => ElementsRepository::ELEMENT_TYPE_ABTEST,
            'options' => Json::encode(['variants' => $variants]),
        ]);

        return $scenarioElementRow;
    }
}
