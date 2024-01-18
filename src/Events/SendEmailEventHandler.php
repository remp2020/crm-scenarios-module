<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use Crm\UsersModule\Models\User\ReachChecker;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\Emitter;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class SendEmailEventHandler extends ScenariosJobsHandler
{
    use NotificationContextTrait;
    use NotificationTemplateParamsTrait;

    public const HERMES_MESSAGE_CODE = 'scenarios-send-email';

    public function __construct(
        JobsRepository $jobsRepository,
        private Emitter $emitter,
        private UsersRepository $usersRepository,
        private SubscriptionsRepository $subscriptionsRepository,
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private PaymentsRepository $paymentsRepository,
        private RecurrentPaymentsResolver $recurrentPaymentsResolver,
        private ReachChecker $reachChecker,
        private AddressesRepository $addressesRepository,
    ) {
        parent::__construct($jobsRepository);
    }

    public function handle(MessageInterface $message): bool
    {
        $job = $this->getJob($message);

        if ($job->state !== JobsRepository::STATE_SCHEDULED) {
            $this->jobError($job, "job in invalid state (expected '" . JobsRepository::STATE_SCHEDULED. "', given '{$job->state}'");
            return true;
        }

        $jobParams = $this->getJobParameters($job);
        if (!isset($jobParams->user_id)) {
            $this->jobError($job, "missing 'user_id' in parameters");
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

        $user = $this->usersRepository->find($jobParams->user_id);
        if (!$user) {
            $this->jobError($job, 'no user with given user_id found');
            return true;
        }
        // Not sending email to people who aren't/shouldn't be reachable
        if (!$this->reachChecker->isReachable($user)) {
            $this->jobsRepository->finishJob($job);
            return true;
        }

        $job = $this->jobsRepository->startJob($job);

        $templateCode = $options->code;
        $templateParams = $this->getNotificationTemplateParams($jobParams);

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
