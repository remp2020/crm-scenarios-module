<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Utils\Json;
use Tomaj\Hermes\MessageInterface;

class RunGenericEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-run-generic';

    private $usersRepository;

    private $genericEventHandlerManager;

    private $emitter;

    public function __construct(
        Emitter $emitter,
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        ScenariosGenericEventsManager $genericEventHandlerManager
    ) {
        parent::__construct($jobsRepository);
        $this->emitter = $emitter;
        $this->usersRepository = $usersRepository;
        $this->genericEventHandlerManager = $genericEventHandlerManager;
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

        $eventOptions = [];
        if (isset($options->options)) {
            foreach ($options->options as $option) {
                $eventOptions[$option->key] = $option->values ?? null;
            }
        }

        $this->jobsRepository->startJob($job);

        try {
            $genericEvent = $this->genericEventHandlerManager->getByCode($options->code);
            $events = $genericEvent->createEvents($eventOptions, $parameters);
            foreach ($events as $event) {
                if (!$event instanceof EventInterface) {
                    $genericEventClassName = get_class($genericEvent);
                    throw new \Exception(
                        "Generic event `{$genericEventClassName}` returned wrong event instance should be `EventInterface`"
                    );
                }
                $this->emitter->emit($event);
            }
        } catch (\Exception $e) {
            $this->jobError($job, $e->getMessage());
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
