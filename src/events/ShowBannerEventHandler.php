<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\MailModule\Mailer\ApplicationMailer;
use Crm\RempModule\Models\Campaign\Api;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class ShowBannerEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-show-banner';

    private $mailer;

    private $usersRepository;

    private $campaignApi;

    public function __construct(
        JobsRepository $jobsRepository,
        ApplicationMailer $mailer,
        UsersRepository $usersRepository,
        Api $campaignApi
    ) {
        parent::__construct($jobsRepository);
        $this->mailer = $mailer;
        $this->usersRepository = $usersRepository;
        $this->campaignApi = $campaignApi;
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

        $user = $this->usersRepository->find($parameters->user_id);
        if (!$user) {
            $this->jobError($job, 'no user with given user_id found');
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

        $this->jobsRepository->startJob($job);

        $bannerId = $options->id;
        $expiresInMinutes = (int) $options->expiresInMinutes;

        $result = $this->campaignApi->showOneTimeBanner($user->id, $bannerId, $expiresInMinutes);

        if (!$result) {
            $this->jobError($job, 'error while setting up banner');
            return true;
        }

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
