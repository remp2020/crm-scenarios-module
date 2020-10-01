<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repository\GeneratedEventsRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Nette\Utils\Json;
use DateInterval;

class BeforeEventGenerator
{
    private $eventStorage;

    private $triggersRepository;

    private $generatedEventsRepository;

    private $dispatcher;

    public function __construct(
        EventsStorage $eventsStorage,
        TriggersRepository $triggersRepository,
        GeneratedEventsRepository $generatedEventsRepository,
        Dispatcher $dispatcher
    ) {
        $this->eventStorage = $eventsStorage;
        $this->triggersRepository = $triggersRepository;
        $this->generatedEventsRepository = $generatedEventsRepository;
        $this->dispatcher = $dispatcher;
    }

    public function generate(): array
    {
        $result = [];

        $triggersSelection = $this->triggersRepository->findByType(TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT);
        foreach ($triggersSelection as $triggerRow) {
            if ($triggerRow->scenario->enabled) {

                /** @var EventGeneratorInterface $eventsGenerator */
                foreach ($this->eventStorage->getEventGenerators() as $code => $eventsGenerator) {
                    if ($triggerRow->event_code === $code) {
                        $options = Json::decode($triggerRow->options, Json::FORCE_ARRAY);
                        $minutes = $options['minutes'];

                        $timeOffset = new DateInterval("PT{$minutes}M");

                        $events = $eventsGenerator->generate($timeOffset);

                        foreach ($events as $event) {
                            if ($this->generatedEventsRepository->exists($triggerRow->id, $code, $event->getId()) === false) {
                                $this->generatedEventsRepository->add($triggerRow->id, $code, $event->getId());

                                $this->dispatcher->dispatchTrigger($triggerRow, $event->getUserId(), $event->getParameters());
                                $result["{$code} (time offset: {$minutes} minutes)"][] = $event;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }
}
