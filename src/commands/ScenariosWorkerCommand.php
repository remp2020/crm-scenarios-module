<?php

namespace Crm\ScenariosModule\Commands;

use Crm\ScenariosModule\Engine\Engine;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScenariosWorkerCommand extends Command
{
    private $engine;

    public function __construct(Engine $engine)
    {
        parent::__construct();
        $this->engine = $engine;
    }

    protected function configure()
    {
        $this->setName('scenarios:worker')
            ->setDescription('Engine for scheduling scenarios jobs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->engine->run();
        return 0;
    }
}
