<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Repository\ElementStatsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\SelectedVariantsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class ABTestDistributeEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-distribute-ab-test';

    public const RESULT_PARAM_SELECTED_VARIANT_INDEX = 'selected_variant_index';
    public const RESULT_PARAM_SELECTED_VARIANT_CODE = 'selected_variant_code';

    private $usersRepository;

    private $selectedVariantRepository;

    private $elementStatsRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        SelectedVariantsRepository $selectedVariantRepository,
        ElementStatsRepository $elementStatsRepository
    ) {
        parent::__construct($jobsRepository);

        $this->usersRepository = $usersRepository;
        $this->selectedVariantRepository = $selectedVariantRepository;
        $this->elementStatsRepository = $elementStatsRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        $job = $this->getJob($message);

        if ($job->state !== JobsRepository::STATE_SCHEDULED) {
            $this->jobError($job, "job in invalid state (expected '" . JobsRepository::STATE_SCHEDULED . "', given '{$job->state}'");
            return true;
        }

        $parameters = $this->getJobParameters($job);
        if (!isset($parameters->user_id)) {
            $this->jobError($job, "missing 'user_id' in parameters");
            return true;
        }
        $userRow = $this->usersRepository->find($parameters->user_id);

        $element = $job->ref('scenarios_elements', 'element_id');
        if (!$element) {
            $this->jobError($job, 'no associated element');
            return true;
        }

        $options = Json::decode($element->options, Json::FORCE_ARRAY);
        if (!isset($options['variants'])) {
            $this->jobError($job, 'missing variants option in associated element');
            return true;
        }

        $this->jobsRepository->startJob($job);

        try {
            $selectedVariantRow = $this->selectedVariantRepository->findByUserAndElement($userRow, $element);

            if (!$selectedVariantRow) {
                $variantCode = $this->selectVariant($options['variants']);

                $selectedVariantRow = $this->selectedVariantRepository->add(
                    $element,
                    $this->usersRepository->find($parameters->user_id),
                    $variantCode
                );
            }
        } catch (\Exception $exception) {
            $this->jobError($job, $exception->getMessage());
            return true;
        }

        $this->jobsRepository->update($job, [
            'result' => Json::encode([
                self::RESULT_PARAM_SELECTED_VARIANT_CODE => $selectedVariantRow->variant_code,
                self::RESULT_PARAM_SELECTED_VARIANT_INDEX => array_search(
                    $selectedVariantRow->variant_code,
                    array_column($options['variants'], 'code'),
                    true
                ),
            ]),
            'state' => JobsRepository::STATE_FINISHED,
            'finished_at' => new \DateTime(),
        ]);

        $this->elementStatsRepository->add($job->element_id, $selectedVariantRow->variant_code);

        return true;
    }

    private function selectVariant(array $variants): string
    {
        $randomInt = random_int(1, 100);
        $highLimit = 0;
        foreach ($variants as $variant) {
            $highLimit += (int)$variant['distribution'];

            if ($highLimit > 100) {
                throw new \UnexpectedValueException("Sum of variant's distribution is more than 100%");
            }

            if ($randomInt <= $highLimit) {
                return $variant['code'];
            }
        }

        throw new \LogicException("Unable to select variant");
    }

    public static function createHermesMessage(int $scenarioJobId): HermesMessage
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $scenarioJobId
        ]);
    }
}
