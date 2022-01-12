<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\ScenariosModule\Events\ScenariosGenericEventsManager;
use Nette\Http\Response;

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

    public function handle(array $params): ApiResponseInterface
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

        $response = new JsonResponse($events);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
