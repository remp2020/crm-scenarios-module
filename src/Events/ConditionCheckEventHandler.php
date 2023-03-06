<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Repository\ElementStatsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Scenarios\ScenariosTriggerCriteriaInterface;
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

    private $subscriptionsRepository;

    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $scenariosCriteriaStorage;

    private $scenariosElementStatsRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ScenariosCriteriaStorage $scenariosCriteriaStorage,
        ElementStatsRepository $elementStatsRepository
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->scenariosCriteriaStorage = $scenariosCriteriaStorage;
        $this->scenariosElementStatsRepository = $elementStatsRepository;
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

        /** @var ?\stdClass $options */
        $options = Json::decode($element->options);
        if (!isset($options->conditions)) {
            $this->jobError($job, 'missing conditions option in associated element');
            return true;
        }

        $job = $this->jobsRepository->startJob($job);

        try {
            $conditionMet = $this->checkConditions($parameters, $options->conditions);
        } catch (ConditionCheckException $e) {
            $this->jobError($job, $e->getMessage());
            return true;
        }

        $this->scenariosElementStatsRepository->add($element->id, $conditionMet ? ElementStatsRepository::STATE_POSITIVE: ElementStatsRepository::STATE_NEGATIVE);

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
     * @throws ConditionCheckException
     */
    private function checkConditions(?\stdClass $jobParameters, ?\stdClass $conditions): bool
    {
        if (!isset($conditions->event)) {
            throw new ConditionCheckException('Condition options is missing event specification');
        }

        $itemQuery = null;
        $itemRow = null;

        // Currently only given (trigger) event checks are supported
        switch ($conditions->event) {
            case 'user':
                // user_id is guaranteed to always be there
                $itemQuery = $this->usersRepository->getTable()->where(['users.id' => $jobParameters->user_id]);
                $itemRow = $this->usersRepository->find($jobParameters->user_id);
                break;
            case 'payment':
                if (!isset($jobParameters->payment_id)) {
                    throw new ConditionCheckException("Job does not have 'payment_id' parameter required by specified condition check");
                }
                $itemQuery = $this->paymentsRepository->getTable()->where(['payments.id' => $jobParameters->payment_id]);
                $itemRow = $this->paymentsRepository->find($jobParameters->payment_id);
                break;
            case 'subscription':
                if (!isset($jobParameters->subscription_id)) {
                    throw new ConditionCheckException("Job does not have 'subscription_id' parameter required by specified condition check");
                }
                $itemQuery = $this->subscriptionsRepository->getTable()->where(['subscriptions.id' => $jobParameters->subscription_id]);
                $itemRow = $this->subscriptionsRepository->find($jobParameters->subscription_id);
                break;
            case 'recurrent_payment':
                if (!isset($jobParameters->recurrent_payment_id)) {
                    throw new ConditionCheckException("Job does not have 'recurrent_payment_id' parameter required by specified condition check");
                }
                $itemQuery = $this->recurrentPaymentsRepository->getTable()->where(['recurrent_payments.id' => $jobParameters->recurrent_payment_id]);
                $itemRow = $this->recurrentPaymentsRepository->find($jobParameters->recurrent_payment_id);
                break;
            case 'trigger':
                foreach ($conditions->nodes as $node) {
                    $criterion = $this->scenariosCriteriaStorage->getEventCriterion($conditions->event, $node->key);
                    if (!$criterion instanceof ScenariosTriggerCriteriaInterface) {
                        throw new ConditionCheckException('Scenario is not evaluable');
                    }

                    $paramValues = [];
                    foreach ($node->params as $param) {
                        $paramValues[$param->key] = $param->values;
                    }

                    if (!$criterion->evaluate($jobParameters, $paramValues)) {
                        return false;
                    }
                }

                return true;
            default:
                $conditionModel = $this->scenariosCriteriaStorage->getConditionModel($conditions->event);
                if ($conditionModel === null) {
                    throw new ConditionCheckException("Not supported condition event {$conditions->event}");
                }

                $itemQuery = $conditionModel->getItemQuery($jobParameters);
                $itemRow = (clone $itemQuery)->fetch();
        }

        foreach ($conditions->nodes as $node) {
            $criterion = $this->scenariosCriteriaStorage->getEventCriterion($conditions->event, $node->key);

            $paramValues = [];
            foreach ($node->params as $param) {
                $paramValues[$param->key] = $param->values;
            }

            if (!$criterion->addConditions($itemQuery, $paramValues, $itemRow)) {
                return false;
            }
        }

        // If item passes all conditions (and therefore an item is fetched), conditions are met
        $item = $itemQuery->fetch();
        if ($item) {
            return true;
        }
        return false;
    }
}
