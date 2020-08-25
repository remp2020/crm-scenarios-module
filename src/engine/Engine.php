<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Events\ConditionCheckEventHandler;
use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Events\OnboardingGoalsCheckEventHandler;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Events\SendEmailEventHandler;
use Crm\ScenariosModule\Events\ShowBannerEventHandler;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Restart\RestartException;
use Tomaj\Hermes\Restart\RestartInterface;
use Tracy\Debugger;

class Engine
{
    public const MAX_RETRY_COUNT = 3;

    private $sleepTime = 5; // in seconds

    private $logger;

    private $jobsRepository;

    private $graphConfiguration;

    private $elementsRepository;

    private $hermesEmitter;

    private $restart;

    private $startTime;

    public function __construct(
        LoggerInterface $logger,
        Emitter $hermesEmitter,
        JobsRepository $jobsRepository,
        GraphConfiguration $graphConfiguration,
        ElementsRepository $elementsRepository
    ) {
        $this->logger = $logger;
        $this->jobsRepository = $jobsRepository;
        $this->graphConfiguration = $graphConfiguration;
        $this->elementsRepository = $elementsRepository;
        $this->hermesEmitter = $hermesEmitter;
        $this->startTime = new DateTime();
    }

    public function setRestartInterface(RestartInterface $restart): void
    {
        $this->restart = $restart;
    }

    public function run(bool $once = false)
    {
        $this->logger->log(LogLevel::INFO, 'Scenarios engine started');
        try {
            while (true) {
                $this->graphConfiguration->reload();

                foreach ($this->jobsRepository->getUnprocessedJobs()->fetchAll() as $job) {
                    $this->processCreatedJob($job);
                }

                foreach ($this->jobsRepository->getFinishedJobs()->fetchAll() as $job) {
                    $this->processFinishedJob($job);
                }

                foreach ($this->jobsRepository->getFailedJobs()->fetchAll() as $job) {
                    $this->processFailedJob($job);
                }

                if ($once) {
                    break;
                }

                if ($this->restart && $this->restart->shouldRestart($this->startTime)) {
                    throw new RestartException('Restart');
                }

                sleep($this->sleepTime);
            }
        } catch (RestartException $exception) {
            $this->logger->notice('Exiting scenarios engine - restart');
        } catch (Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
        }
    }

    private function processFailedJob(ActiveRow $job)
    {
        if ($job->retry_count >= self::MAX_RETRY_COUNT) {
            $this->logger->log(LogLevel::ERROR, "Failed job found, it has already failed {$job->retry_count} times, cancelling", $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        } else {
            $this->logger->log(LogLevel::WARNING, 'Failed job found, rescheduling', $this->jobLoggerContext($job));
            $this->jobsRepository->update($job, [
                'state' => JobsRepository::STATE_CREATED,
                'started_at' => null,
                'finished_at' => null,
                'result' => null,
                'retry_count' => $job->retry_count + 1,
            ]);
        }
    }

    private function processFinishedJob(ActiveRow $job)
    {
        $this->logger->log(LogLevel::INFO, 'Processing finished job', $this->jobLoggerContext($job));

        try {
            if ($job->trigger_id) {
                $this->scheduleNextAfterTrigger($job);
            } elseif ($job->element_id) {
                $this->scheduleNextAfterElement($job);
            } else {
                $this->logger->log(LogLevel::ERROR, 'Scenarios job without associated trigger or element', $this->jobLoggerContext($job));
            }
        } catch (InvalidJobException | JsonException $e) {
            $this->logger->log(LogLevel::ERROR, $e->getMessage(), $this->jobLoggerContext($job));
        } catch (NodeDeletedException $e) {
            // This can happen if user updates running scenario
            $this->logger->log(LogLevel::WARNING, $e->getMessage(), $this->jobLoggerContext($job));
        } finally {
            $this->jobsRepository->delete($job);
        }
    }

    private function processCreatedJob(ActiveRow $job)
    {
        $this->logger->log(LogLevel::INFO, 'Processing newly created job', $this->jobLoggerContext($job));

        if ($job->trigger_id) {
            // Triggers can be directly finished
            $this->jobsRepository->update($job, [
                'started_at' => new DateTime(),
                'finished_at' => new DateTime(),
                'state' => JobsRepository::STATE_FINISHED
            ]);
        } elseif ($job->element_id) {
            $this->processJobElement($job);
        } else {
            $this->logger->log(LogLevel::ERROR, 'Scenarios job without associated trigger or element', $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        }
    }

    private function processJobElement(ActiveRow $job)
    {
        $element = $this->elementsRepository->find($job->element_id);
        $options = Json::decode($element->options, Json::FORCE_ARRAY);

        try {
            switch ($element->type) {
                case ElementsRepository::ELEMENT_TYPE_GOAL:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(OnboardingGoalsCheckEventHandler::createHermesMessage($job->id));
                    break;
                case ElementsRepository::ELEMENT_TYPE_EMAIL:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(SendEmailEventHandler::createHermesMessage($job->id));
                    break;
                case ElementsRepository::ELEMENT_TYPE_BANNER:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(ShowBannerEventHandler::createHermesMessage($job->id));
                    break;
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(ConditionCheckEventHandler::createHermesMessage($job->id));
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(SegmentCheckEventHandler::createHermesMessage($job->id));
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    if (!isset($options['minutes'])) {
                        throw new InvalidJobException("Associated job element has no 'minutes' option");
                    }
                    $this->jobsRepository->startJob($job);
                    $this->hermesEmitter->emit(FinishWaitEventHandler::createHermesMessage($job->id, (int) $options['minutes']));
                    break;
                default:
                    throw new InvalidJobException('Associated job element has wrong type');
                    break;
            }
        } catch (InvalidJobException $exception) {
            $this->logger->log(LogLevel::ERROR, $exception->getMessage(), $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        }
    }

    /**
     * @param ActiveRow $job
     *
     * @throws NodeDeletedException
     * @throws \Nette\Utils\JsonException
     */
    private function scheduleNextAfterTrigger(ActiveRow $job)
    {
        foreach ($this->graphConfiguration->triggerDescendants($job->trigger_id) as $elementId) {
            $this->jobsRepository->addElement($elementId, Json::decode($job->parameters, Json::FORCE_ARRAY));
        }
    }

    /**
     * @param ActiveRow $job
     *
     * @throws InvalidJobException
     * @throws NodeDeletedException
     * @throws \Nette\Utils\JsonException
     */
    private function scheduleNextAfterElement(ActiveRow $job)
    {
        $element = $this->elementsRepository->find($job->element_id);
        if (!$element) {
            throw new InvalidJobException("no element found with id {$job->element_id}");
        }

        switch ($element->type) {
            case ElementsRepository::ELEMENT_TYPE_CONDITION:
                $result = Json::decode($job->result, Json::FORCE_ARRAY);
                if (!array_key_exists(ConditionCheckEventHandler::RESULT_PARAM_CONDITION_MET, $result)) {
                    throw new InvalidJobException("condition job results do not contain required parameter '". ConditionCheckEventHandler::RESULT_PARAM_CONDITION_MET ."'");
                }
                $conditionMet = (bool) $result[ConditionCheckEventHandler::RESULT_PARAM_CONDITION_MET];
                $descendantIds = $this->graphConfiguration->elementDescendants($job->element_id, $conditionMet);
                break;
            case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                $result = Json::decode($job->result);
                if (!isset($result->in)) {
                    throw new InvalidJobException("segment job results do not contain required parameter 'in'");
                }
                $descendantIds = $this->graphConfiguration->elementDescendants($job->element_id, (bool) $result->in);
                break;
            case ElementsRepository::ELEMENT_TYPE_GOAL:
                $result = Json::decode($job->result, Json::FORCE_ARRAY);
                $timeouted = $result[OnboardingGoalsCheckEventHandler::RESULT_PARAM_TIMEOUT] ?? false;
                $completed = $result[OnboardingGoalsCheckEventHandler::RESULT_PARAM_GOALS_COMPLETED] ?? false;

                if ($timeouted) {
                    $descendantIds = $this->graphConfiguration->elementDescendants($job->element_id, false);
                } elseif ($completed) {
                    $descendantIds = $this->graphConfiguration->elementDescendants($job->element_id, true);
                } else {
                    throw new InvalidJobException('goal job is neither completed nor timed out: ');
                }
                break;
            default:
                $descendantIds = $this->graphConfiguration->elementDescendants($job->element_id);
        }

        foreach ($descendantIds as $elementId) {
            $this->jobsRepository->addElement($elementId, Json::decode($job->parameters, Json::FORCE_ARRAY));
        }
    }

    private function jobLoggerContext(ActiveRow $job): array
    {
        $params = Json::decode($job->parameters, Json::FORCE_ARRAY);
        $redactedParams = [];
        // Do not log passwords in cleartext
        if ($params) {
            foreach ($params as $name => $value) {
                if ($name == 'pass' || $name == 'password') {
                    $redactedParams[$name] = 'REDACTED';
                } else {
                    $redactedParams[$name] = $value;
                }
            }
        }

        return [
            'trigger_id' => $job->trigger_id,
            'element_id' => $job->element_id,
            'state' => $job->state,
            'retry_count' => $job->retry_count,
            'parameters' => Json::encode($redactedParams),
            'result' => $job->result,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
            'updated_at' => $job->updated_at,
        ];
    }
}
