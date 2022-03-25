<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ScenariosInfoApiHandler extends ApiHandler
{
    private $scenariosRepository;

    public function __construct(
        ScenariosRepository $scenariosRepository
    ) {
        $this->scenariosRepository = $scenariosRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'id', InputParam::REQUIRED),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, ['status' => 'error', 'message' => 'Cannot authorize user']);
            return $response;
        }

        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->hasError();
        if ($error) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => "Wrong request parameters [{$error}]."]);
            return $response;
        }

        $params = $paramsProcessor->getValues();

        try {
            $result = $this->scenariosRepository->getScenario((int)$params['id']);
        } catch (\Exception $exception) {
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, ['status' => 'error', 'message' => $exception->getMessage()]);
            return $response;
        }

        if (!$result) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, ['status' => 'error', 'message' => "Scenario with ID [{$params['id']}] not found."]);
            return $response;
        }

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
