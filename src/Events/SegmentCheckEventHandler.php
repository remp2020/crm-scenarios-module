<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\Database\Repository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\ScenariosModule\Repositories\ElementStatsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\UnexpectedValueException;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class SegmentCheckEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-segment-check';

    private $usersRepository;

    private $segmentFactory;

    private $segmentsRepository;

    private $subscriptionsRepository;

    private $paymentsRepository;

    private $elementStatsRepository;

    public function __construct(
        SegmentFactoryInterface $segmentFactory,
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository,
        PaymentsRepository $paymentsRepository,
        SegmentsRepository $segmentsRepository,
        ElementStatsRepository $elementStatsRepository,
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->segmentFactory = $segmentFactory;
        $this->segmentsRepository = $segmentsRepository;
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

        $segmentRow = $this->segmentsRepository->findByCode($options->code);
        if (!$segmentRow) {
            $this->jobError($job, 'missing segment for code: ' . $options->code);
            return true;
        }

        $job = $this->jobsRepository->startJob($job);

        try {
            switch ($segmentRow->table_name) {
                case 'users':
                    $repository = $this->usersRepository;
                    $id = $parameters->user_id;
                    break;
                case 'subscriptions':
                    $repository = $this->subscriptionsRepository;
                    $id = $parameters->subscription_id;
                    break;
                case 'payments':
                    $repository = $this->paymentsRepository;
                    $id = $parameters->payment_id;
                    break;
                default:
                    throw new SegmentCheckException("Unsupported segment source table: {$segmentRow->table_name}");
            }

            $inSegment = $this->checkInSegment($repository, $id, $options->code);
        } catch (SegmentCheckException $e) {
            $this->jobError($job, $e->getMessage());
            return true;
        }

        $this->elementStatsRepository->add($element->id, $inSegment ? ElementStatsRepository::STATE_POSITIVE : ElementStatsRepository::STATE_NEGATIVE);

        $this->jobsRepository->update($job, [
            'result' => Json::encode(['in' => $inSegment]),
            'state' => JobsRepository::STATE_FINISHED,
            'finished_at' => new DateTime(),
        ]);
        return true;
    }

    public static function createHermesMessage($jobId)
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $jobId,
        ]);
    }

    private function checkInSegment(Repository $repository, int $id, string $segmentCode): bool
    {
        $row = $repository->find($id);
        if (!$row) {
            throw new SegmentCheckException("Row with given ID doesn't exist");
        }

        try {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
        } catch (UnexpectedValueException $e) {
            throw new SegmentCheckException('Segment does not exist');
        }

        return $segment->isIn('id', $id);
    }
}
