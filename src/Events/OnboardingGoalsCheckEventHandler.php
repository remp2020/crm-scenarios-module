<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\OnboardingModule\Repositories\OnboardingGoalsRepository;
use Crm\OnboardingModule\Repositories\UserOnboardingGoalsRepository;
use Crm\ScenariosModule\Repositories\ElementStatsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\MessageInterface;

class OnboardingGoalsCheckEventHandler extends ScenariosJobsHandler
{
    public const HERMES_MESSAGE_CODE = 'scenarios-onboarding-goals-check';
    public const RESULT_PARAM_TIMEOUT = 'timeout';
    public const RESULT_PARAM_GOALS_COMPLETED = 'goals_completed';

    private $usersRepository;

    private $hermesEmitter;

    private $onboardingGoalsRepository;

    private $userOnboardingGoalsRepository;

    private $elementStatsRepository;

    public function __construct(
        JobsRepository $jobsRepository,
        UsersRepository $usersRepository,
        OnboardingGoalsRepository $onboardingGoalsRepository,
        UserOnboardingGoalsRepository $userOnboardingGoalsRepository,
        Emitter $hermesEmitter,
        ElementStatsRepository $elementStatsRepository
    ) {
        parent::__construct($jobsRepository);
        $this->usersRepository = $usersRepository;
        $this->hermesEmitter = $hermesEmitter;
        $this->onboardingGoalsRepository = $onboardingGoalsRepository;
        $this->userOnboardingGoalsRepository = $userOnboardingGoalsRepository;
        $this->elementStatsRepository = $elementStatsRepository;
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

        $job = $this->jobsRepository->startJob($job);

        try {
            $onboardingGoalsIds = $this->loadOnboardingGoals($options->codes);
        } catch (OnboardingGoalsCheckException $e) {
            $this->jobError($job, $e->getMessage());
            return true;
        }

        // each user who entered scenario with goal node has to have user onboarding goal entry
        $this->ensureUserHasOnboardingGoals($user->id, $onboardingGoalsIds, $job);

        // check if user completed goals
        if ($this->userCompletedOnboardingGoals($user->id, $onboardingGoalsIds, $job)) {
            $this->jobsRepository->update($job, [
                'result' => Json::encode([self::RESULT_PARAM_GOALS_COMPLETED => true]),
                'state' => JobsRepository::STATE_FINISHED,
                'finished_at' => new DateTime()
            ]);

            $this->elementStatsRepository->add($job->element_id, ElementStatsRepository::STATE_POSITIVE);
            return true;
        }

        // check if timeout is reached
        // note: we want to wait till timeout is reached with finishing job;
        //       so there is no check if all user's goals timed out before reaching this point in time
        if (isset($options->timeoutMinutes)) {
            $timeoutMinutes = (int) $options->timeoutMinutes;
            $timeoutDate = DateTime::from($job->created_at)->add(new \DateInterval("PT{$timeoutMinutes}M"));

            if (new DateTime("now") >= $timeoutDate) {
                $this->timeoutUserOnboardingGoals($user->id, $onboardingGoalsIds);

                $this->jobsRepository->update($job, [
                    'result' => Json::encode([self::RESULT_PARAM_TIMEOUT => true]),
                    'state' => JobsRepository::STATE_FINISHED,
                    'finished_at' => new DateTime(),
                ]);

                $this->elementStatsRepository->add($job->element_id, ElementStatsRepository::STATE_NEGATIVE);
                return true;
            }
        }

        // If goals are not completed, reschedule another check
        $job = $this->jobsRepository->scheduleJob($job);
        $this->hermesEmitter->emit(self::createHermesMessage($job->id, (int) $options->recheckPeriodMinutes));
        return true;
    }

    /**
     * Loads IDs of onboarding goals and checks if all goals exist.
     * @param  array $onboardingGoalCodes Contents of `$options->codes`
     * @return array Returns array of integers with IDs of onboarding goals.
     * @throws OnboardingGoalsCheckException
     */
    private function loadOnboardingGoals(array $onboardingGoalCodes): array
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

        return $onboardingGoalIds;
    }

    private function timeoutUserOnboardingGoals(int $userId, array $onboardingGoalsIds)
    {
        $timedoutAt = new DateTime();
        foreach ($onboardingGoalsIds as $onboardingGoalId) {
            $this->userOnboardingGoalsRepository->timeout($userId, $onboardingGoalId, $timedoutAt);
        }
    }

    private function ensureUserHasOnboardingGoals(int $userId, array $onboardingGoalsIDs, ActiveRow $job)
    {
        foreach ($onboardingGoalsIDs as $onboardingGoalID) {
            $userLastGoal = $this->userOnboardingGoalsRepository
                ->userLastGoal($userId, $onboardingGoalID);

            // no goal; create new
            if ($userLastGoal === null) {
                $this->userOnboardingGoalsRepository->add($userId, $onboardingGoalID);
                continue;
            }

            // user's goal not completed or timed out
            if ($userLastGoal->completed_at === null
                && $userLastGoal->timedout_at === null) {
                // "touch" user's goal entry to indicate when it was checked by scenario
                $this->userOnboardingGoalsRepository->update($userLastGoal, ['updated_at' => new DateTime()]);
                continue;
            }

            // user's goal is completed or timed out; but it's current user's goal entry
            if ($userLastGoal->updated_at >= $job->created_at) {
                continue;
            }

            // there is no current or previous goal we could use for scenario; create new
            $this->userOnboardingGoalsRepository->add($userId, $onboardingGoalID);
        }
    }

    private function userCompletedOnboardingGoals(int $userId, array $onboardingGoalsIDs, ActiveRow $job): bool
    {
        $completedGoalsCount = $this->userOnboardingGoalsRepository
            ->userCompletedGoals($userId, $onboardingGoalsIDs)
            ->where(['completed_at >= ?' => $job->created_at])
            ->count('*');

        return $completedGoalsCount === count($onboardingGoalsIDs);
    }
}
