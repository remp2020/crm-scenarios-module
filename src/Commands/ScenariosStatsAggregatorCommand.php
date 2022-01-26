<?php

namespace Crm\ScenariosModule\Commands;

use Crm\ScenariosModule\Repository\ElementStatsRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ScenariosStatsAggregatorCommand extends Command
{
    private const AGGREGATION_STEP_MINUTES = 60;
    private const DEFAULT_UNTIL_TRESHOLD = '- 24 hours';
    private const DEFAULT_FROM_TRESHOLD = '- 5 days';

    private $elementStatsRepository;

    private $triggerStatsRepository;

    public function __construct(
        ElementStatsRepository $elementStatsRepository,
        TriggerStatsRepository $triggerStatsRepository
    ) {
        parent::__construct();

        $this->elementStatsRepository = $elementStatsRepository;
        $this->triggerStatsRepository = $triggerStatsRepository;
    }

    protected function configure()
    {
        $this->setName('scenarios:aggregate_stats')
            ->setDescription('Aggregate scenarios statistics ')
            ->addOption(
                'from',
                null,
                InputOption::VALUE_OPTIONAL,
                'Date from'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from = $input->getOption('from');

        $fromDateTime = new DateTime($from ?? self::DEFAULT_FROM_TRESHOLD);
        $maxUpperBound = new DateTime(self::DEFAULT_UNTIL_TRESHOLD);

        $aggregatedTriggersCount = 0;
        $aggregatedElementsCount = 0;

        $lowerBound = DateTime::createFromFormat('Y-m-d H:i:s', $fromDateTime->format('Y-m-d H') . ':00:00');
        $upperBound = clone $lowerBound;
        $maxUpperBound = DateTime::createFromFormat('Y-m-d H:i:s', $maxUpperBound->format('Y-m-d H') . ':00:00');

        $output->writeln("Aggregating data from: " . $lowerBound->format('Y-m-d H:i:s') . " until: " . $maxUpperBound->format('Y-m-d H:i:s'));

        while (true) {
            $upperBound->add(new \DateInterval('PT' . self::AGGREGATION_STEP_MINUTES . 'M'));
            if ($upperBound >= $maxUpperBound) {
                break;
            }

            $aggregatedTriggersCount += $this->aggregateTriggersData($lowerBound, $upperBound);
            $aggregatedElementsCount += $this->aggregateElementsData($lowerBound, $upperBound);

            $lowerBound->add(new \DateInterval('PT' . self::AGGREGATION_STEP_MINUTES . 'M'));
        }

        $output->writeln("Number of triggers aggregated: {$aggregatedTriggersCount}");
        $output->writeln("Number of elements aggregated: {$aggregatedElementsCount}");

        return Command::SUCCESS;
    }

    private function aggregateTriggersData(DateTime $fromDateTime, DateTime $toDateTime): int
    {
        $count = 0;
        $dataToAggregateSelection = $this->triggerStatsRepository->getDataToAggregate($fromDateTime, $toDateTime);

        $triggersStats= $dataToAggregateSelection->select('trigger_id, state, SUM(count) AS total')
            ->group('trigger_id, state')
            ->fetchAll();

        if ($triggersStats) {
            $count = $dataToAggregateSelection->delete();

            foreach ($triggersStats as $triggersStat) {
                $this->triggerStatsRepository->add(
                    $triggersStat['trigger_id'],
                    $triggersStat['state'],
                    (int)$triggersStat['total'],
                    self::AGGREGATION_STEP_MINUTES,
                    $fromDateTime
                );
            }
        }

        return $count;
    }

    private function aggregateElementsData(DateTime $fromDateTime, DateTime $toDateTime): int
    {
        $count = 0;
        $dataToAggregateSelection = $this->elementStatsRepository->getDataToAggregate($fromDateTime, $toDateTime);

        $elementStats = $dataToAggregateSelection->select('element_id, state, SUM(count) AS total')
            ->group('element_id, state')
            ->fetchAll();

        if ($elementStats) {
            $count = $dataToAggregateSelection->delete();

            foreach ($elementStats as $elementStat) {
                $this->elementStatsRepository->add(
                    $elementStat['element_id'],
                    $elementStat['state'],
                    $elementStat['total'],
                    self::AGGREGATION_STEP_MINUTES,
                    $fromDateTime
                );
            }
        }

        return $count;
    }
}
