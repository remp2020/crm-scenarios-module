<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class SendEmailEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-send-email';

    private $usersRepository;

    private $emitter;

    private $subscriptionsRepository;

    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    public function __construct(
        Emitter $emitter,
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->emitter = $emitter;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
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

        // We automatically insert password/subscription/payment as email template parameters (if found)
        $password = $parameters->password ?? null;
        $subscription = $parameters->subscription_id ? $this->subscriptionsRepository->find($parameters->subscription_id) : null;
        $payment = $parameters->payment_id ? $this->paymentsRepository->find($parameters->payment_id) : null;
        $recurrentPayment = $parameters->recurrent_payment_id ?
            $this->recurrentPaymentsRepository->find($parameters->recurrent_payment_id) : null;

        $templateParams = ['email' => $user->email];
        if ($password) {
            $templateParams['password'] = $password;
        }
        if ($subscription) {
            $templateParams['subscription'] = $subscription->toArray();
        }
        if ($payment) {
            $templateParams['payment'] = $payment->toArray();
        }
        if ($recurrentPayment) {
            $templateParams['recurrent_payment'] = $recurrentPayment->toArray();
        }

        $this->emitter->emit(new NotificationEvent($user, $templateCode, $templateParams));

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
