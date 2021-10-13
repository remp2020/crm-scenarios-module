<?php

namespace Crm\ScenariosModule\Commands;

use Crm\ScenariosModule\Events\TriggerHandlers\TestUserHandler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\Emitter;

class TestUserCommand extends Command
{
    private $hermesEmitter;

    public function __construct(Emitter $emitter)
    {
        parent::__construct();
        $this->hermesEmitter = $emitter;
    }

    protected function configure()
    {
        $this->setName('scenarios:test_user')
            ->setDescription("Fires 'test_user' scenario trigger with given user_id")
            ->addOption(
                'user_id',
                null,
                InputOption::VALUE_REQUIRED,
                'User ID that will be passed as a parameter'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $userId = $input->getOption('user_id');
        if (!$userId) {
            throw new \InvalidArgumentException('Missing --user_id option');
        }
        $this->hermesEmitter->emit(TestUserHandler::createHermesMessage($userId));

        $output->writeln("<info>Event 'scenarios-test-user' with UserID={$userId} fired</info>");
        return Command::SUCCESS;
    }
}
