<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
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
    use NotificationContextTrait;

    public const HERMES_MESSAGE_CODE = 'scenarios-send-email';

    private $usersRepository;

    private $emitter;

    private $subscriptionsRepository;

    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $recurrentPaymentsResolver;

    public function __construct(
        Emitter $emitter,
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        SubscriptionsRepository $subscriptionsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsResolver $recurrentPaymentsResolver
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->emitter = $emitter;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->recurrentPaymentsResolver = $recurrentPaymentsResolver;
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
        // Not sending email to inactive user
        if (!$user->active) {
            $this->jobsRepository->finishJob($job);
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
        $subscription = isset($parameters->subscription_id) ? $this->subscriptionsRepository->find($parameters->subscription_id) : null;
        $payment = isset($parameters->payment_id) ? $this->paymentsRepository->find($parameters->payment_id) : null;

        $recurrentPayment = null;
        if (isset($parameters->recurrent_payment_id)) {
            $recurrentPayment = $this->recurrentPaymentsRepository->find($parameters->recurrent_payment_id);
        } elseif ($payment !== null) {
            $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment) ?? null;
        }

        $subscriptionType = null;
        if ($subscription !== null) {
            $subscriptionType = $subscription->subscription_type;
        } elseif ($payment !== null) {
            $subscriptionType = $payment->subscription_type;
        } elseif ($recurrentPayment !== null) {
            $subscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        }

        $templateParams = ['email' => $user->email];
        if ($password) {
            $templateParams['password'] = $password;
        }
        if ($subscription) {
            $templateParams['subscription'] = $subscription->toArray();
        }
        if ($subscriptionType) {
            $templateParams['subscription_type'] = $subscriptionType->toArray();
        }
        if ($payment) {
            $templateParams['payment'] = $payment->toArray();
        }
        if ($recurrentPayment) {
            $templateParams['recurrent_payment'] = $recurrentPayment->toArray();
        }

        $notificationEvent = new NotificationEvent(
            $this->emitter,
            $user,
            $templateCode,
            $templateParams,
            null,
            [],
            null,
            $this->getNotificationContext($job)
        );
        $this->emitter->emit($notificationEvent);

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
