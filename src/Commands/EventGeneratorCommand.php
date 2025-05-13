<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ScenariosModule\Events\BeforeEventGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EventGeneratorCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private BeforeEventGenerator $beforeEventGenerator,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('scenarios:event_generator')
            ->setDescription('Generates before events');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $results = $this->beforeEventGenerator->generate();

        ksort($results);
        foreach ($results as $code => $eventTimeOffsets) {
            ksort($eventTimeOffsets);
            foreach ($eventTimeOffsets as $minutes => $events) {
                $output->writeln(
                    " * <comment>" . count($events) . " events</comment> generated for trigger: "
                    . "<comment>{$code}</comment> (time offset: <comment>{$minutes}</comment> minutes)"
                );
            }
        }

        $output->writeln('Done');

        return Command::SUCCESS;
    }
}
