<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\MailModule\Mailer\ApplicationMailer;
use Crm\OnboardingModule\Repository\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repository\UserOnboardingGoalsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\MessageInterface;

class OnboardingGoalsCheckEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-onboarding-goals-check';
    public const RESULT_PARAM_TIMEOUT = 'timeout';
    public const RESULT_PARAM_GOALS_COMPLETED = 'goals_completed';

    private $mailer;

    private $usersRepository;

    private $hermesEmitter;

    private $onboardingGoalsRepository;

    private $userOnboardingGoalsRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        ApplicationMailer $mailer,
        UsersRepository $usersRepository,
        OnboardingGoalsRepository $onboardingGoalsRepository,
        UserOnboardingGoalsRepository $userOnboardingGoalsRepository,
        Emitter $hermesEmitter
    ) {
        parent::__construct($jobsRepository);
        $this->mailer = $mailer;
        $this->usersRepository = $usersRepository;
        $this->hermesEmitter = $hermesEmitter;
        $this->onboardingGoalsRepository = $onboardingGoalsRepository;
        $this->userOnboardingGoalsRepository = $userOnboardingGoalsRepository;
    }

    public static function createHermesMessage($jobId, int $minutesDelay = null)
    {
        $executeAt = null;
        if ($minutesDelay !== null) {
            $executeAt = (float) (new DateTime("now + {$minutesDelay} minutes"))->getTimestamp();
        }

        return new HermesMessage(self::HERMES_MESSAGE_CODE, [
            'job_id' => $jobId
        ], null, null, $executeAt);
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
        if (!isset($options->codes)) {
            $this->jobError($job, "missing 'codes' option in associated element");
            return true;
        }
        if (!isset($options->recheckPeriodMinutes)) {
            $this->jobError($job, "missing 'recheckPeriodMinutes' option in associated element");
            return true;
        }

        $this->jobsRepository->startJob($job);

        // check if timeout is reached
        if (isset($options->timeoutMinutes)) {
            $timeoutMinutes = (int) $options->timeoutMinutes;
            $timeoutDate = DateTime::from($job->created_at)->add(new \DateInterval("PT{$timeoutMinutes}M"));

            if (new DateTime("now") >= $timeoutDate) {
                $this->jobsRepository->update($job, [
                    'result' => Json::encode([self::RESULT_PARAM_TIMEOUT => true]),
                    'state' => JobsRepository::STATE_FINISHED,
                    'finished_at' => new DateTime(),
                ]);
                return true;
            }
        }

        try {
            if ($this->userCompletedOnboardingGoals($parameters->user_id, $options->codes)) {
                $this->jobsRepository->update($job, [
                    'result' => Json::encode([self::RESULT_PARAM_GOALS_COMPLETED => true]),
                    'state' => JobsRepository::STATE_FINISHED,
                    'finished_at' => new DateTime()
                ]);
                return true;
            }
        } catch (OnboardingGoalsCheckException $e) {
            $this->jobError($job, $e->getMessage());
            return true;
        }

        // If goals are not completed, reschedule another check
        $this->jobsRepository->scheduleJob($job);
        $this->hermesEmitter->emit(self::createHermesMessage($job->id, (int) $options->recheckPeriodMinutes));
        return true;
    }

    private function userCompletedOnboardingGoals($userId, array $onboardingGoalCodes): bool
    {
        $onboardingGoalCodes = array_unique($onboardingGoalCodes);
        $onboardingGoals = $this->onboardingGoalsRepository->getTable()
            ->where('code IN (?)', $onboardingGoalCodes);

        $onboardingGoalIds = [];
        $missingGoalCodes = array_fill_keys($onboardingGoalCodes, true);
        foreach ($onboardingGoals as $goal) {
            $onboardingGoalIds[] = $goal->id;

            if (isset($missingGoalCodes[$goal->code])) {
                unset($missingGoalCodes[$goal->code]);
            }
        }

        if (count($missingGoalCodes) > 0) {
            throw new OnboardingGoalsCheckException('Missing onboarding goals:' . implode(',', array_keys($missingGoalCodes)));
        }

        $completedGoalsCount = $this->userOnboardingGoalsRepository->getTable()
            ->where(['user_id' => $userId])
            ->where('completed_at IS NOT NULL')
            ->where('onboarding_goal_id IN (?)', $onboardingGoalIds)
            ->count('*');

        return $completedGoalsCount === count($onboardingGoalCodes);
    }
}
