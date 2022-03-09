<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\ScenariosModule\Repository\ScenarioInvalidDataException;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Http\Request;
use Nette\Http\Response;
use Tracy\Debugger;

class ScenariosCreateApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $request;

    private $scenariosRepository;

    public function __construct(
        Request $request,
        ScenariosRepository $scenariosRepository
    ) {
        $this->request = $request;
        $this->scenariosRepository = $scenariosRepository;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ApiResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }

        $contentType = $this->request->getHeader('Content-Type');

        // check if Content Type header contains `application/json`
        $contentTypes = explode(';', $contentType);
        if (!in_array('application/json', array_map('trim', $contentTypes))) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Incorrect Content-Type [{$contentType}]. Expected 'application/json'."]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }

        $requestValidationResult = $this->validateInput(
            __DIR__ . '/scenarios-create-request.schema.json',
            $this->rawPayload()
        );

        if ($requestValidationResult->hasErrorResponse()) {
            return $requestValidationResult->getErrorResponse();
        }

        $data = (array)$requestValidationResult->getParsedObject();

        try {
            $scenario = $this->scenariosRepository->createOrUpdate($data);
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

        if (!$scenario) {
            $response = new JsonResponse(['status' => 'error', 'message' => "Scenario with provided ID [{$data['id']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $result = $this->scenariosRepository->getScenario($scenario->id);
        if (!$result) {
            // any error at this moment means there is issue on our side
            $message = "Unable to load scenario with ID [{$scenario->id}]";
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
