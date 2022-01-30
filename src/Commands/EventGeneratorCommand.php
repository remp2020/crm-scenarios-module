<?php

namespace Crm\ScenariosModule\Commands;

use Crm\ScenariosModule\Events\BeforeEventGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EventGeneratorCommand extends Command
{
    private $beforeEventGenerator;

    public function __construct(BeforeEventGenerator $beforeEventGenerator)
    {
        parent::__construct();

        $this->beforeEventGenerator = $beforeEventGenerator;
    }

    protected function configure()
    {
        $this->setName('scenarios:event_generator')
            ->setDescription('Generates before events');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("Generating before events - STARTED");

        $results = $this->beforeEventGenerator->generate();

        foreach ($results as $code => $events) {
            $output->writeln(count($events) . " events generated for trigger: {$code}");
        }

        $output->writeln("Generating before events - DONE");

        return Command::SUCCESS;
    }
}
