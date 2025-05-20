<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Repositories\JobsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

abstract class ScenariosJobsHandler implements HandlerInterface
{
    protected $jobsRepository;

    public function __construct(JobsRepository $jobsRepository)
    {
        $this->jobsRepository = $jobsRepository;
    }

    protected function getJob(MessageInterface $message): ActiveRow
    {
        $payload = $message->getPayload();
        if (!isset($payload['job_id'])) {
            throw new \Exception('unable to handle event: job_id missing');
        }

        $job = $this->jobsRepository->find($payload['job_id']);
        if (!$job) {
            throw new \Exception("no scenarios job with id={$payload['job_id']} found");
        }

        return $job;
    }

    protected function getJobParameters(ActiveRow $job): ?\stdClass
    {
        return Json::decode($job->parameters);
    }

    protected function jobError(ActiveRow $job, string $message, bool $retry = false)
    {
        $this->jobsRepository->update($job, [
            'state' => JobsRepository::STATE_FAILED,
            'result' => Json::encode([
                'error' => $message,
                'retry' => $retry,
            ]),
        ]);
    }
}
