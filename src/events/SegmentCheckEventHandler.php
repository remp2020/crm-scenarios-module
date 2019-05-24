<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\SegmentModule\SegmentFactory;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;
use Nette\UnexpectedValueException;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class SegmentCheckEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-segment-check';

    private $usersRepository;

    private $segmentFactory;

    public function __construct(
        SegmentFactory $segmentFactory,
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->segmentFactory = $segmentFactory;
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
            $this->jobError($job, "no associated element");
            return true;
        }

        $options = Json::decode($element->options);
        if (!isset($options->code)) {
            $this->jobError($job, 'missing code option in associated element');
            return true;
        }

        $this->jobsRepository->update($job, ['state' => JobsRepository::STATE_STARTED, 'started_at' => new DateTime()]);

        try {
            $inSegment = $this->checkUserInSegment($parameters->user_id, $options->code);
        } catch (SegmentCheckException $e) {
            $this->jobError($job, $e->getMessage());
            return true;
        }

        $this->jobsRepository->update($job, [
            'result' => Json::encode(['in' => $inSegment]),
            'state' => JobsRepository::STATE_FINISHED,
            'finished_at' => new DateTime()
        ]);
        return true;
    }

    public static function createHermesMessage($jobId)
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $jobId
        ]);
    }

    private function checkUserInSegment($userId, $segmentCode)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new SegmentCheckException("User with given ID doesn't exist");
        }
        try {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
        } catch (UnexpectedValueException $e) {
            throw new SegmentCheckException('Segment does not exist');
        }

        return $segment->isIn('id', $userId);
    }
}
