<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Engine\Validator;

use Crm\ApplicationModule\Models\Event\EventGeneratorOutputProviderInterface;
use Crm\ApplicationModule\Models\Event\EventsStorage;
use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Exception;

class TriggerOutputParamsRetriever
{
    public function __construct(
        private readonly TriggerManager $triggerManager,
        private readonly EventsStorage $eventsStorage,
    ) {
    }

    /**
     * @param array $trigger Trigger structure from scenarios
     * @return string[]
     */
    public function retrieve(array $trigger): array
    {
        $triggerType = $trigger['type'];
        $eventKey = $trigger['event']['code'];

        if ($triggerType === TriggersRepository::TRIGGER_TYPE_EVENT) {
            try {
                return $this->triggerManager
                    ->getTriggerHandlerByKey($eventKey)
                    ->getOutputParams();
            } catch (Exception $exception) {
                throw new TriggerOutputParamsRetrieveException(
                    sprintf("Trigger handler with key '%s' not found.", $eventKey),
                    previous: $exception
                );
            }
        }

        if ($triggerType === TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT) {
            try {
                $eventGenerator = $this->eventsStorage->getEventGeneratorByCode($eventKey);
            } catch (Exception $exception) {
                throw new TriggerOutputParamsRetrieveException(
                    sprintf("Event generator with key '%s' not found.", $eventKey),
                    previous: $exception
                );
            }

            if (!($eventGenerator instanceof EventGeneratorOutputProviderInterface)) {
                throw new TriggerOutputParamsRetrieveException('No output params defined.');
            }

            return $eventGenerator->getOutputParams();
        }

        throw new TriggerOutputParamsRetrieveException(sprintf("Unknown trigger type '%s'.", $triggerType));
    }
}
