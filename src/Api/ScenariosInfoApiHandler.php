<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ScenariosModule\Repository\AccordsRepository;
use Nette\Application\BadRequestException;
use Nette\Http\Response;

class ScenariosInfoApiHandler extends ApiHandler
{
    private $accordsRepository;

    public function __construct(
        AccordsRepository $accordsRepository
    ) {
        $this->accordsRepository = $accordsRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'id', InputParam::REQUIRED),
        ];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }

        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Wrong request parameters [{$error}]."]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $params = $paramsProcessor->getValues();

        try {
            $result = $this->accordsRepository->getAccord($params['id']);
        } catch (BadRequestException $exception) {
            $response = new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        } catch (\Exception $exception) {
            $response = new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
            return $response;
        }

        if (!$result) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Accord with ID [{$params['id']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
