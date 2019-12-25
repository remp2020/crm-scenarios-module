<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApplicationModule\Criteria\CriteriaParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Nette\Http\Response;

class ScenariosCriteriaHandler extends ApiHandler
{
    private $criteriaStorage;

    public function __construct(ScenariosCriteriaStorage $criteriaStorage)
    {
        $this->criteriaStorage = $criteriaStorage;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $criteriaArray = $this->criteriaStorage->getCriteria();
        $result = [];
        foreach ($criteriaArray as $table => $tableCriteria) {
            foreach ($tableCriteria as $key => $criteria) {
                /** @var CriteriaParam[] $params */
                $params = $criteria->params();
                $paramsArray = [];
                foreach ($params as $param) {
                    $paramsArray[$param->key()] = $param->blueprint();
                }

                $result[$table][] = [
                    'key' => $key,
                    'label' => $criteria->label(),
                    'params' => $paramsArray,
                ];
            }
        }

        $resultData = [];
        foreach ($result as $table => $criteria) {
            $resultData[] = [
                'table' => $table,
                'criteria' => $criteria,
            ];
        }

        $response = new JsonResponse(['blueprint' => $resultData]);
        $response->setHttpCode(Response::S200_OK);

        return $response;
    }
}
