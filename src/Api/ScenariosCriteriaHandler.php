<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApplicationModule\Models\Criteria\ScenarioCriteriaParamInterface;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaStorage;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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

    public function handle(array $params): ResponseInterface
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

        $response = new JsonApiResponse(Response::S200_OK, ['blueprint' => $resultData]);

        return $response;
    }
}
