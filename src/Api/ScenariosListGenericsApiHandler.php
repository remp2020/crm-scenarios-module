<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ScenariosModule\Events\ScenariosGenericEventsManager;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ScenariosListGenericsApiHandler extends ApiHandler
{
    private $manager;

    public function __construct(ScenariosGenericEventsManager $manager)
    {
        $this->manager = $manager;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        $events = [];
        foreach ($this->manager->getAllRegisteredEvents() as $code => $event) {
            $paramsArray = [];
            foreach ($event->getParams() as $param) {
                $paramsArray[$param->key()] = $param->blueprint();
            }

            $events[] = [
                'code' => $code,
                'label' => $event->getLabel(),
                'options' => $paramsArray
            ];
        }

        $response = new JsonApiResponse(Response::S200_OK, $events);
        return $response;
    }
}
