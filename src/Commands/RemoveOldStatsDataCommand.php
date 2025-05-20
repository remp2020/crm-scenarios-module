<?php

namespace Crm\ScenariosModule\Commands;

use Crm\ScenariosModule\Repositories\ElementStatsRepository;
use Crm\ScenariosModule\Repositories\TriggerStatsRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveOldStatsDataCommand extends Command
{
    private const DEFAULT_TIME_PERIOD = '-31 days';

    private $elementStatsRepository;

    private $triggerStatsRepository;

    public function __construct(
        ElementStatsRepository $elementStatsRepository,
        TriggerStatsRepository $triggerStatsRepository,
    ) {
        parent::__construct();

        $this->elementStatsRepository = $elementStatsRepository;
        $this->triggerStatsRepository = $triggerStatsRepository;
    }

    protected function configure()
    {
        $this->setName('scenarios:remove_stats')
            ->setDescription('Remove old statistic data')
            ->addOption(
                'date-to',
                null,
                InputOption::VALUE_OPTIONAL,
                'Date to remove all statistic data created before',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $to = $input->getOption('date-to');
        if (!$to) {
            $to = self::DEFAULT_TIME_PERIOD;
        }

        $toDateTime = new DateTime($to);

        $output->writeln("Deleting stats until: " . $toDateTime->format('Y-m-d H:i:s'));

        $deletedElementsCount = $this->elementStatsRepository->getTable()
            ->where('created_at <=', $toDateTime)
            ->delete();

        $output->writeln("Deleted elements' stats count: {$deletedElementsCount}");

        $deletedTriggersCount = $this->triggerStatsRepository->getTable()
            ->where('created_at <=', $toDateTime)
            ->delete();

        $output->writeln("Deleted triggers' stats count: {$deletedTriggersCount}");

        $output->writeln("DONE");

        return Command::SUCCESS;
    }
}
