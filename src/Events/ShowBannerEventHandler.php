<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\UsersModule\Models\User\ReachChecker;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class ShowBannerEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-show-banner';

    private UsersRepository $usersRepository;

    private Emitter $emitter;

    private ReachChecker $reachChecker;

    public function __construct(
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        Emitter $emitter,
        ReachChecker $reachChecker
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->emitter = $emitter;
        $this->reachChecker = $reachChecker;
    }

    public function handle(MessageInterface $message): bool
    {
        $job = $this->getJob($message);

        if ($job->state !== JobsRepository::STATE_SCHEDULED) {
            $this->jobError($job, "job in invalid state (expected '" . JobsRepository::STATE_SCHEDULED. "', given '{$job->state}'");
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
        if (!isset($options->id)) {
            $this->jobError($job, 'missing id option in associated element');
            return true;
        }
        if (!isset($options->expiresInMinutes)) {
            $this->jobError($job, 'missing expiresInMinutes option in associated element');
            return true;
        }

        $user = $this->usersRepository->find($parameters->user_id);
        if (!$user) {
            $this->jobError($job, 'no user with given user_id found');
            return true;
        }
        // Not showing banner to people who are/should be not reachable
        if (!$this->reachChecker->isReachable($user)) {
            $this->jobsRepository->finishJob($job);
            return true;
        }

        $job = $this->jobsRepository->startJob($job);

        $bannerId = $options->id;
        $expiresInMinutes = (int) $options->expiresInMinutes;

        $this->emitter->emit(new BannerEvent($user, $bannerId, $expiresInMinutes));

        $this->jobsRepository->finishJob($job);
        return true;
    }

    public static function createHermesMessage($scenarioJobId)
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $scenarioJobId
        ]);
    }
}
