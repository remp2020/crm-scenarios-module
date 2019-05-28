<?php

namespace Crm\ScenariosModule\Engine;

use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Events\SegmentCheckEventHandler;
use Crm\ScenariosModule\Events\SendEmailEventHandler;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tomaj\Hermes\Emitter;
use Tracy\Debugger;

class Engine
{
    public const MAX_RETRY_COUNT = 3;

    private $sleepTime = 100;

    private $logger;

    private $startTime;

    private $jobsRepository;

    private $graphConfiguration;

    private $elementsRepository;

    private $hermesEmitter;

    public function __construct(
        LoggerInterface $logger,
        Emitter $hermesEmitter,
        JobsRepository $jobsRepository,
        GraphConfiguration $graphConfiguration,
        ElementsRepository $elementsRepository
    ) {
        $this->logger = $logger;
        $this->startTime = new DateTime();
        $this->jobsRepository = $jobsRepository;
        $this->graphConfiguration = $graphConfiguration;
        $this->elementsRepository = $elementsRepository;
        $this->hermesEmitter = $hermesEmitter;
    }

    public function run(bool $once = false)
    {
        $this->log(LogLevel::INFO, 'Scenarios engine started');
        try {
            while (true) {
                $this->graphConfiguration->reloadIfOutdated();

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

                sleep($this->sleepTime);
            }
        } catch (Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
        }
    }

    private function processFailedJob(ActiveRow $job)
    {
        if ($job->retry_count >= self::MAX_RETRY_COUNT) {
            $this->log(LogLevel::ERROR, "Failed job job found, it has already failed {$job->retry_count} times, cancelling", $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        } else {
            $this->log(LogLevel::WARNING, 'Failed job found, rescheduling', $this->jobLoggerContext($job));
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
        $this->log(LogLevel::INFO, 'Processing finished job', $this->jobLoggerContext($job));

        try {
            if ($job->trigger_id) {
                $this->scheduleNextAfterTrigger($job);
            } elseif ($job->element_id) {
                $this->scheduleNextAfterElement($job);
            } else {
                $this->log(LogLevel::ERROR, 'Scenarios job without associated trigger or element', $this->jobLoggerContext($job));
            }
        } catch (InvalidJobException $exception) {
            $this->log(LogLevel::ERROR, $exception->getMessage(), $this->jobLoggerContext($job));
        } finally {
            $this->jobsRepository->delete($job);
        }
    }

    private function processCreatedJob(ActiveRow $job)
    {
        $this->log(LogLevel::INFO, 'Processing newly created job', $this->jobLoggerContext($job));

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
            $this->log(LogLevel::ERROR, 'Scenarios job without associated trigger or element', $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        }
    }

    private function processJobElement(ActiveRow $job)
    {
        $element = $this->elementsRepository->find($job->element_id);
        $options = Json::decode($element->options, Json::FORCE_ARRAY);

        try {
            switch ($element->type) {
                case ElementsRepository::ELEMENT_TYPE_EMAIL:
                    $this->jobsRepository->scheduleJob($job);
                    $this->hermesEmitter->emit(SendEmailEventHandler::createHermesMessage($job->id));
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
            $this->log(LogLevel::ERROR, $exception->getMessage(), $this->jobLoggerContext($job));
            $this->jobsRepository->delete($job);
        }
    }

    private function scheduleNextAfterTrigger(ActiveRow $job)
    {
        foreach ($this->graphConfiguration->triggerDescendants($job->trigger_id) as $elementId) {
            $this->jobsRepository->addElement($elementId, Json::decode($job->parameters, Json::FORCE_ARRAY));
        }
    }

    private function scheduleNextAfterElement(ActiveRow $job)
    {
        $element = $this->elementsRepository->find($job->element_id);
        if (!$element) {
            throw new InvalidJobException("no element found with id {$job->element_id}");
        }

        $direction = GraphConfiguration::POSITIVE_PATH_DIRECTION;
        if ($element->type === ElementsRepository::ELEMENT_TYPE_SEGMENT) {
            $results = Json::decode($job->results);
            if (!isset($results->in)) {
                throw new InvalidJobException("job results do not contain required parameter 'in'");
            }

            $direction = ((bool) $results->in) ? GraphConfiguration::POSITIVE_PATH_DIRECTION : GraphConfiguration::NEGATIVE_PATH_DIRECTION;
        }

        foreach ($this->graphConfiguration->elementDescendants($job->element_id, $direction) as $elementId) {
            $this->jobsRepository->addElement($elementId, Json::decode($job->parameters, Json::FORCE_ARRAY));
        }
    }

    private function log($level, string $message, array $context = [])
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
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
