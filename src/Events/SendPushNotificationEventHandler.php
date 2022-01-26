<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\OneSignalModule\Events\OneSignalNotificationEvent;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class SendPushNotificationEventHandler extends ScenariosJobsHandler
{
    use NotificationContextTrait;

    public const HERMES_MESSAGE_CODE = 'scenarios-send-push-notification';

    private $emitter;

    private $usersRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        Emitter $emitter,
        UsersRepository $usersRepository
    ) {
        parent::__construct($jobsRepository);

        $this->emitter = $emitter;
        $this->usersRepository = $usersRepository;
    }

    public function handle(MessageInterface $message): bool
    {
        if (!class_exists(OneSignalNotificationEvent::class)) {
            throw new \Exception('Unable to send push notification, OneSignal module has not been installed yet.');
        }

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
        if (!isset($options->application, $options->template)) {
            $this->jobError($job, "missing 'application' or 'template' option in associated element");
            return true;
        }

        $this->emitter->emit(new OneSignalNotificationEvent(
            $this->emitter,
            $options->application,
            $user,
            $options->template,
            array_merge(
                (array) $options,
                (array) $parameters
            ),
            null,
            null,
            $this->getNotificationContext($job)
        ));

        $this->jobsRepository->finishJob($job);
        return true;
    }

    public static function createHermesMessage(int $scenarioJobId): HermesMessage
    {
        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $scenarioJobId
        ]);
    }
}
