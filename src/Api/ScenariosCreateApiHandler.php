<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ScenariosModule\Repository\ScenarioInvalidDataException;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Http\Request;
use Nette\Http\Response;
use Tracy\Debugger;

class ScenariosCreateApiHandler extends ApiHandler
{
    private $request;

    private $scenariosRepository;

    public function __construct(
        Request $request,
        ScenariosRepository $scenariosRepository
    ) {
        $this->request = $request;
        $this->scenariosRepository = $scenariosRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_POST, 'id', InputParam::OPTIONAL),
            new InputParam(InputParam::TYPE_POST, 'title', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'triggers', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'elements', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_POST, 'visual', InputParam::REQUIRED),
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

        // TODO: process application/json raw body properly
        // TODO: throw error when content type is different?
        if ($this->request->getHeader('Content-Type') === 'application/json') {
            $body = json_decode($this->request->getRawBody());
            $_POST['id'] = $body->id ?? null;
            $_POST['title'] = $body->title ?? null;
            $_POST['triggers'] = $body->triggers ?? null;
            $_POST['elements'] = $body->elements ?? null;
            $_POST['visual'] = $body->visual ?? null;
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
            $scenarioID = $this->scenariosRepository->createOrUpdate($params);
        } catch (ScenarioInvalidDataException $exception) {
            $response = new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
            $response->setHttpCode(Response::S409_CONFLICT);
            return $response;
        } catch (\Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
            $response = new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
            return $response;
        }

        if (!$scenarioID) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Provided scenario ID [{$params['id']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $result = $this->scenariosRepository->getScenario($scenarioID);
        if (!$result) {
            // any error at this moment means there is issue on our side
            $message = "Unable to find created / updated scenario with ID [{$scenarioID}]";
            Debugger::log($message, Debugger::EXCEPTION);
            $response = new JsonResponse(['status' => 'error', 'message' => $message]);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
            return $response;
        }

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S201_CREATED);
        return $response;
    }
}
