<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ScenariosModule\Events\RepeatingTriggerGenerator;
use DateInterval;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RepeatingTriggerGeneratorCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private RepeatingTriggerGenerator $repeatingTriggerGenerator,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('scenarios:repeating_trigger_generator')
            ->setDescription('Enrolls segment users into scenarios started by repeating triggers')
            ->addOption(
                'now',
                null,
                InputOption::VALUE_REQUIRED,
                'Wall-clock time to evaluate the schedules against, e.g. "2026-08-03 09:05". Defaults to now.',
            )
            ->addOption(
                'scan-minutes',
                null,
                InputOption::VALUE_REQUIRED,
                'How long after its scheduled time an occurrence may still be fired. Keep it at least as '
                . 'long as the cron interval, or occurrences are missed. Defaults to 20.',
            )
            ->addOption(
                'catch-up-minutes',
                null,
                InputOption::VALUE_REQUIRED,
                'How far back a due occurrence is still looked up. One that is found but is already older '
                . 'than --scan-minutes is recorded as skipped instead of being fired. Defaults to 60.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = null;
        if ($input->getOption('now')) {
            try {
                $now = new DateTimeImmutable($input->getOption('now'));
            } catch (Exception $exception) {
                $output->writeln("<error>Invalid --now value: {$exception->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        try {
            $scanMinutes = $this->minutesOption($input, 'scan-minutes');
            $catchUpMinutes = $this->minutesOption($input, 'catch-up-minutes');
        } catch (InvalidArgumentException $exception) {
            $output->writeln("<error>{$exception->getMessage()}</error>");
            return Command::FAILURE;
        }

        if ($scanMinutes !== null) {
            $this->repeatingTriggerGenerator->setScanWindow(new DateInterval("PT{$scanMinutes}M"));
        }
        if ($catchUpMinutes !== null) {
            $this->repeatingTriggerGenerator->setCatchUpWindow(new DateInterval("PT{$catchUpMinutes}M"));
        }

        $results = $this->repeatingTriggerGenerator->generate($now);

        ksort($results);
        foreach ($results as $triggerId => $occurrences) {
            ksort($occurrences);
            foreach ($occurrences as $occurredAt => $jobsCreated) {
                $output->writeln(
                    " * <comment>{$jobsCreated} users</comment> enrolled by trigger "
                    . "<comment>#{$triggerId}</comment> (occurrence: <comment>{$occurredAt}</comment>)",
                );
            }
        }

        $output->writeln('Done');

        return Command::SUCCESS;
    }

    private function minutesOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);
        if ($value === null) {
            return null;
        }

        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($minutes === false) {
            throw new InvalidArgumentException("Option --{$name} has to be a positive integer.");
        }

        return $minutes;
    }
}
