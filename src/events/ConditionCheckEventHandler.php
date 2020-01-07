<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\SegmentModule\SegmentFactory;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class ConditionCheckEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-condition-check';

    public const RESULT_PARAM_CONDITION_MET = 'conditions_met';

    private $usersRepository;

    private $segmentFactory;

    private $subscriptionsRepository;

    private $paymentsRepository;

    private $scenariosCriteriaStorage;

    public function __construct(
        SegmentFactory $segmentFactory,
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository,
        PaymentsRepository $paymentsRepository,
        ScenariosCriteriaStorage $scenariosCriteriaStorage
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->segmentFactory = $segmentFactory;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->scenariosCriteriaStorage = $scenariosCriteriaStorage;
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

        $element = $job->ref('scenarios_elements', 'element_id');
        if (!$element) {
            $this->jobError($job, 'no associated element');
            return true;
        }

        $options = Json::decode($element->options);
        if (!isset($options->conditions)) {
            $this->jobError($job, 'missing conditions option in associated element');
            return true;
        }

        $this->jobsRepository->startJob($job);

        try {
            $conditionMet = $this->checkConditions($parameters, $options->conditions);
        } catch (ConditionCheckException $e) {
            $this->jobError($job, $e->getMessage());
            return true;
        }

        $this->jobsRepository->update($job, [
            'result' => Json::encode([self::RESULT_PARAM_CONDITION_MET => $conditionMet]),
            'state' => JobsRepository::STATE_FINISHED,
            'finished_at' => new DateTime(),
        ]);
        return true;
    }

    public static function createHermesMessage($jobId)
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $jobId
        ]);
    }


    /**
     * @param $jobParameters
     * @param $conditions
     *
     * @return bool
     * @throws ConditionCheckException
     */
    private function checkConditions($jobParameters, $conditions): bool
    {
        if (!isset($conditions->event)) {
            throw new ConditionCheckException('Condition options is missing event specification');
        }

        $itemQuery = null;

        // Currently only given (trigger) event checks are supported
        switch ($conditions->event) {
            case 'payment':
                if (!isset($jobParameters->payment_id)) {
                    throw new ConditionCheckException("Job does not have 'payment_id' parameter required by specified condition check");
                }
                $itemQuery = $this->paymentsRepository->getTable()->where(['payments.id' => $jobParameters->payment_id]);
                break;
            case 'subscription':
                if (!isset($jobParameters->subscription_id)) {
                    throw new ConditionCheckException("Job does not have 'subscription_id' parameter required by specified condition check");
                }
                $itemQuery = $this->subscriptionsRepository->getTable()->where(['subscriptions.id' => $jobParameters->subscription_id]);
                break;
            default:
                throw new ConditionCheckException("Not supported condition event {$conditions->event}");
        }

        foreach ($conditions->nodes as $node) {
            $criterion = $this->scenariosCriteriaStorage->getEventCriterion($conditions->event, $node->key);
            $criterion->addCondition($itemQuery, $node->values);
        }

        // If item passes all conditions (and therefore an item is fetched), conditions are met
        $item = $itemQuery->fetch();
        if ($item) {
            return true;
        }
        return false;
    }
}
