<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Models\Event\EventGeneratorInterface;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ScenariosModule\Engine\Dispatcher;
use Crm\ScenariosModule\Repositories\GeneratedEventsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use DateInterval;
use Nette\Utils\Json;

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
            if (!$triggerRow->scenario->enabled) {
                continue;
            }

            /** @var EventGeneratorInterface $eventsGenerator */
            foreach ($this->eventStorage->getEventGenerators() as $code => $eventsGenerator) {
                if ($triggerRow->event_code !== $code) {
                    continue;
                }

                $options = Json::decode($triggerRow->options, Json::FORCE_ARRAY);
                $minutes = $options['minutes'];
                $timeOffset = new DateInterval("PT{$minutes}M");

                $events = $eventsGenerator->generate($timeOffset);

                foreach ($events as $event) {
                    if ($this->generatedEventsRepository->exists($triggerRow->id, $code, $event->getId()) === false) {
                        $this->generatedEventsRepository->add($triggerRow->id, $code, $event->getId());

                        $this->dispatcher->dispatchTrigger($triggerRow, $event->getUserId(), $event->getParameters(), [
                            JobsRepository::CONTEXT_BEFORE_EVENT => $code,
                        ]);
                        $result["{$code} (time offset: {$minutes} minutes)"][] = $event;
                    }
                }
            }
        }

        return $result;
    }
}
