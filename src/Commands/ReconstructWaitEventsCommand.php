<?php

namespace Crm\ScenariosModule\Commands;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ScenariosModule\Events\FinishWaitEventHandler;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\Emitter;

class ReconstructWaitEventsCommand extends Command
{
    private JobsRepository $jobsRepository;

    private Emitter $emitter;

    public function __construct(
        JobsRepository $jobsRepository,
        Emitter $emitter
    ) {
        parent::__construct();
        $this->jobsRepository = $jobsRepository;
        $this->emitter = $emitter;
    }

    protected function configure()
    {
        $this->setName('scenarios:reconstruct_wait_events')
            ->setDescription('In case the Redis DB was flushed/migrated, this command emits the wait events again.')
            ->addOption(
                'started_at_from',
                null,
                InputOption::VALUE_REQUIRED,
                'Use only jobs that started since the provided date'
            )
            ->addOption(
                'past_events',
                null,
                InputOption::VALUE_NONE,
                'Emit events that should have been already executed'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new DateTime();
        $jobsQuery = $this->jobsRepository->getTable()
            ->where('state = ?', JobsRepository::STATE_STARTED)
            ->where('element.type = ?', ElementsRepository::ELEMENT_TYPE_WAIT)
            ->where('created_at < ?', $now)
            ->order('scenarios_jobs.id ASC');

        if ($startedAtFrom = $input->getOption('started_at_from')) {
            $jobsQuery->where('started_at >= ?', DateTime::from($startedAtFrom));
        }

        $pastEvents = $input->getOption('past_events');
        $step = 1000;
        $lastId = 0;
        $now = new DateTime();

        do {
            $processed = 0;

            $jobs = (clone $jobsQuery)
                ->where('scenarios_jobs.id > ?', $lastId)
                ->limit($step);

            foreach ($jobs as $job) {
                $processed += 1;
                $lastId = $job->id;

                /** @var DateTime $startedAt */
                $startedAt = $job->started_at;
                $options = Json::decode($job->element->options);
                $minutes = $options->minutes;
                $executeAt = $startedAt->modifyClone("+{$minutes} minutes");

                $output->write("Processing job <comment>{$job->id}</comment>, execute date <info>{$executeAt->format(DATE_RFC3339)}</info>: ");

                // Don't emit events that should already have been executed.
                if (!$pastEvents && $executeAt <= $now) {
                    $output->writeln('Skipping, execution in past');
                    continue;
                }

                $this->emitter->emit(
                    new HermesMessage(
                        FinishWaitEventHandler::HERMES_MESSAGE_CODE,
                        ['job_id' => $job->id],
                        null,
                        null,
                        (float) $executeAt->getTimestamp()
                    )
                );
                $output->writeln('OK');
            }
        } while ($processed > 0);

        $output->writeln("DONE");

        return Command::SUCCESS;
    }
}
