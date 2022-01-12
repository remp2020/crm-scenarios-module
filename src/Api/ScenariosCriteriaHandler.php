<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\Scenarios\ScenarioCriteriaParamInterface;
use Nette\Http\Response;

class ScenariosCriteriaHandler extends ApiHandler
{
    private $criteriaStorage;

    public function __construct(ScenariosCriteriaStorage $criteriaStorage)
    {
        $this->criteriaStorage = $criteriaStorage;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $criteriaArray = $this->criteriaStorage->getCriteria();
        $result = [];
        foreach ($criteriaArray as $event => $tableCriteria) {
            foreach ($tableCriteria as $key => $criteria) {
                /** @var ScenarioCriteriaParamInterface[] $params */
                $params = $criteria->params();
                $paramsArray = [];
                foreach ($params as $param) {
                    $paramsArray[] = $param->blueprint();
                }

                $result[$event][] = [
                    'key' => $key,
                    'label' => $criteria->label(),
                    'params' => $paramsArray,
                ];
            }
        }

        $resultData = [];
        foreach ($result as $event => $criteria) {
            $resultData[] = [
                'event' => $event,
                'criteria' => $criteria,
            ];
        }

        $response = new JsonResponse(['blueprint' => $resultData]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
