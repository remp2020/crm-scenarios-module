<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Models\Scenario\TriggerManager;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ScenariosListTriggersApiHandler extends ApiHandler
{
    public function __construct(
        private readonly TriggerManager $triggerManager,
    ) {
        parent::__construct();
    }

    public function handle(array $params): ResponseInterface
    {
        $events = [];

        foreach ($this->triggerManager->getTriggerHandlers() as $triggerHandler) {
            $events[] = [
                'name' => $triggerHandler->getName(),
                'key' => $triggerHandler->getKey(),
                'output_params' => $triggerHandler->getOutputParams(),
            ];
        }

        return new JsonApiResponse(Response::S200_OK, $events);
    }
}
