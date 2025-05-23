<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

class ScenariosInfoApiHandler extends ApiHandler
{
    private $scenariosRepository;

    public function __construct(
        ScenariosRepository $scenariosRepository,
    ) {
        $this->scenariosRepository = $scenariosRepository;
    }

    public function params(): array
    {
        return [
            (new GetInputParam('id'))->setRequired(),
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
