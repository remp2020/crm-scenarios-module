<?php

namespace Crm\ScenariosModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\ScenariosModule\Repository\EventsRepository;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Output\OutputInterface;

class ScenariosSeeder implements ISeeder
{
    private $eventsRepository;

    public function __construct(
        EventsRepository $eventsRepository
    ) {
        $this->eventsRepository = $eventsRepository;
    }

    public function seed(OutputInterface $output)
    {
        //TODO: load here all events which extend 'League\Event\AbstractEvent'
        $events = ['user_created', 'new_payment'];

        foreach ($events as $event) {
            $eventExists = $this->eventsRepository->findBy('code', $event);

            if (!$eventExists) {
                $eventData = [
                    'code' => $event,
                    'created_at' =>  new DateTime(),
                    'modified_at' => new DateTime(),
                ];
                $this->eventsRepository->insert($eventData);
                $output->writeln("  <comment>* Scenarios' event type for <info>{$event}</info> created</comment>");
            } else {
                $output->writeln("  * Scenarios' event type <info>{$event}</info> exists");
            }
        }
    }
}
