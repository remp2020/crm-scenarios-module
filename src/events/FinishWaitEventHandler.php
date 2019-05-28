<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Repository\JobsRepository;
use Nette\Utils\DateTime;
use Tomaj\Hermes\MessageInterface;

class FinishWaitEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-finish-wait';

    public function __construct(JobsRepository $jobsRepository)
    {
        parent::__construct($jobsRepository);
    }

    public function handle(MessageInterface $message): bool
    {
        $job = $this->getJob($message);

        if ($job->state !== JobsRepository::STATE_STARTED) {
            $this->jobError($job, "job in invalid state (expected '" . JobsRepository::STATE_STARTED . "', given '{$job->state}'");
            return true;
        }

        $this->jobsRepository->update($job, [
            'state' => JobsRepository::STATE_FINISHED,
            'finished_at' => new DateTime(),
        ]);
        return true;
    }

    public static function createHermesMessage($jobId, int $minutesDelay)
    {
        $executeAt = (float) (new DateTime("now + {$minutesDelay} minutes"))->getTimestamp();
        return new HermesMessage(self::HERMES_MESSAGE_CODE, ['job_id' => $jobId], null, null, $executeAt);
    }
}
