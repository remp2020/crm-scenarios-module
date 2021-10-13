<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Events\ConditionCheckEventHandler;
use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Events\OnboardingGoalsCheckEventHandler;
use Crm\ScenariosModule\Events\RunGenericEventHandler;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Events\SendEmailEventHandler;
use Crm\ScenariosModule\Events\SendPushNotificationEventHandler;
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
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;
use Tracy\Debugger;

class Engine
{
    public const MAX_RETRY_COUNT = 3;

    // 50000us = 50ms = 0.05s
    private $minSleepTime = 50000; // in microseconds
    
    // 1s
    private $maxSleepTime = 1000000; // in microseconds
    
    private $minGraphReloadDelay = 60; // in seconds

    private $logger;

    private $jobsRepository;

    private $graphConfiguration;

    private $elementsRepository;

    private $hermesEmitter;

    /** @var ShutdownInterface */
    private $shutdown;

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

    public function setMinSleepTime(int $minSleepTime): void
    {
        $this->minSleepTime = $minSleepTime;
    }

    public function setMaxSleepTime(int $maxSleepTime): void
    {
        $this->maxSleepTime = $maxSleepTime;
    }

    public function setShutdownInterface(ShutdownInterface $shutdown): void
    {
        $this->shutdown = $shutdown;
    }
    
    public function run(?int $times = null): void
    {
        $this->logger->log(LogLevel::INFO, 'Scenarios engine started');
        $i = $times;
        try {
            $emptyIterationCounter = 0;
            while ($times === null || $i > 0) {
                // For fixed amount of iterations, always reload graph
                $this->graphConfiguration->reload($times !== null ? 0 : $this->minGraphReloadDelay);

                $jobs = $this->jobsRepository->getTable()
                    ->where('state IN (?)', [
                        JobsRepository::STATE_CREATED, JobsRepository::STATE_FINISHED, JobsRepository::STATE_FAILED
                    ])
                    ->order(
                        'FIELD(state, ?, ?, ?), updated_at',
                        JobsRepository::STATE_CREATED,
                        JobsRepository::STATE_FINISHED,
                        JobsRepository::STATE_FAILED
                    )
                    ->fetchAll();

                $emptyIterationCounter++;
                foreach ($jobs as $job) {
                    $emptyIterationCounter = 0; // if jobs are found, iteration is not empty
                    if ($job->state === JobsRepository::STATE_CREATED) {
                        $this->processCreatedJob($job);
                    } elseif ($job->state === JobsRepository::STATE_FINISHED) {
                        $this->processFinishedJob($job);
                    } elseif ($job->state === JobsRepository::STATE_FAILED) {
                        $this->processFailedJob($job);
                    }
                }

                // for fixed amount of iterations, do not sleep or wait for shutdown
                if ($times !== null) {
                    $i--;
                } else {
                    [$sleepTime, $ifMaxDelayIsUsed] = $this->calculateDelay($emptyIterationCounter);
                    if ($ifMaxDelayIsUsed) {
                        // do not increase iterations without upper bound to avoid overflow
                        $emptyIterationCounter = max(0, $emptyIterationCounter-1);
                    }
                    
                    usleep($sleepTime);
                    if ($this->shutdown && $this->shutdown->shouldShutdown($this->startTime)) {
                        throw new ShutdownException('Shutdown');
                    }
                }
            }
        } catch (ShutdownException $exception) {
            $this->logger->notice('Exiting scenarios engine - shutdown');
        } catch (Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
        }
    }


    /**
     * Calculates exponential delay.
     *
     * @param int   $attempt The attempt number used to calculate the delay.
     *                       First attempt should be 0.
     * @param float $exp     by default 1.5
     *
     * @return array [int $delayInMicroseconds, bool $ifMaxDelayIsUsed]
     */
    private function calculateDelay(int $attempt, float $exp = 1.5): array
    {
        // look for maximum $attempt that will not exceed $maxSleepTime in the exponential express below
        if ($attempt >= log($this->maxSleepTime/$this->minSleepTime, $exp)) {
            return [$this->maxSleepTime, true];
        }
        
        $delay = min($this->maxSleepTime, (int)floor($exp ** $attempt) * $this->minSleepTime);
        return [$delay, false];
    }

    private function processFailedJob(ActiveRow $job)
    {
        $result = Json::decode($job->result);
        $shouldRetry = $result->retry ?? false;

        if (!$shouldRetry) {
            $this->logger->log(LogLevel::ERROR, 'Failed job found and retry is not allowed, cancelling', $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        } elseif ($job->retry_count >= self::MAX_RETRY_COUNT) {
            $this->logger->log(LogLevel::ERROR, "Failed job found, it has already failed {$job->retry_count} times, cancelling", $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        } else {
            $this->logger->log(LogLevel::WARNING, 'Failed job found (retry allowed), rescheduling', $this->jobLoggerContext($job));
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
                    $this->hermesEmitter->emit(OnboardingGoalsCheckEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_EMAIL:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(SendEmailEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_BANNER:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(ShowBannerEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_GENERIC:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(RunGenericEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(ConditionCheckEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(SegmentCheckEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    if (!isset($options['minutes'])) {
                        throw new InvalidJobException("Associated job element has no 'minutes' option");
                    }
                    $this->jobsRepository->startJob($job);
                    $this->hermesEmitter->emit(FinishWaitEventHandler::createHermesMessage($job->id, (int) $options['minutes']), HermesMessage::PRIORITY_DEFAULT);
                    break;
                case ElementsRepository::ELEMENT_TYPE_PUSH_NOTIFICATION:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(SendPushNotificationEventHandler::createHermesMessage($job->id), HermesMessage::PRIORITY_DEFAULT);
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
            $this->jobsRepository->addElement(
                $elementId,
                Json::decode($job->parameters, Json::FORCE_ARRAY),
                $job->context ? Json::decode($job->context, Json::FORCE_ARRAY) : null
            );
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
            $this->jobsRepository->addElement(
                $elementId,
                Json::decode($job->parameters, Json::FORCE_ARRAY),
                $job->context ? Json::decode($job->context, Json::FORCE_ARRAY) : null
            );
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
