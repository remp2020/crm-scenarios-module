<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ScenariosModule\Events\ScenariosGenericEventsManager;
use Nette\Http\Response;

class ScenariosListGenericsApiHandler extends ApiHandler
{
    private $manager;

    public function __construct(ScenariosGenericEventsManager $manager)
    {
        $this->manager = $manager;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
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
                'params' => $paramsArray
            ];
        }

        $response = new JsonResponse($events);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
