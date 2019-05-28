<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\MailModule\Mailer\ApplicationMailer;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class SendEmailEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-send-email';

    private $mailer;

    private $usersRepository;

    public function __construct(JobsRepository $jobsRepository, ApplicationMailer $mailer, UsersRepository $usersRepository)
    {
        parent::__construct($jobsRepository);
        $this->mailer = $mailer;
        $this->usersRepository = $usersRepository;
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
        if (!isset($options->code)) {
            $this->jobError($job, 'missing code option in associated element');
            return true;
        }

        $this->jobsRepository->startJob($job);

        $templateCode = $options->code;

        // TODO throw error for some email templates if password is missing (each template should specify required parameters?)
        $password = $parameters->password ?? null;

        $templateParams = array_merge(
            ['email' => $user->email],
            $password ? ['password' => $password] : []
        );

        $result = $this->mailer->send($user->email, $templateCode, $templateParams);
        if (!$result) {
            $this->jobError($job, 'error while sending email');
            return true;
        }

        $this->jobsRepository->finishJob($job);
        return true;
    }

    public static function createHermesMessage($jobId)
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $jobId
        ]);
    }
}
