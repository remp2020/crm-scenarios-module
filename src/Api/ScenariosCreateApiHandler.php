<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\ScenariosModule\Repositories\ScenarioInvalidDataException;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Http\Request;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
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

    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token'])) {
            return new JsonApiResponse(
                Response::S403_FORBIDDEN,
                ['status' => 'error', 'message' => 'Cannot authorize user']
            );
        }

        $contentType = $this->request->getHeader('Content-Type');

        // check if Content Type header contains `application/json`
        $contentTypes = explode(';', $contentType);
        if (!in_array('application/json', array_map('trim', $contentTypes), true)) {
            return new JsonApiResponse(
                Response::S400_BAD_REQUEST,
                ['status' => 'error', 'message' => "Incorrect Content-Type [{$contentType}]. Expected 'application/json'."]
            );
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
            return new JsonApiResponse(
                Response::S409_CONFLICT,
                ['status' => 'error', 'message' => $exception->getMessage()]
            );
        } catch (\Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
            return new JsonApiResponse(
                Response::S500_INTERNAL_SERVER_ERROR,
                ['status' => 'error', 'message' => $exception->getMessage()]
            );
        }

        if (!$scenario) {
            return new JsonApiResponse(
                Response::S404_NOT_FOUND,
                ['status' => 'error', 'message' => "Scenario with provided ID [{$data['id']}] not found."]
            );
        }

        $result = $this->scenariosRepository->getScenario($scenario->id);
        if (!$result) {
            // any error at this moment means there is issue on our side
            $message = "Unable to load scenario with ID [{$scenario->id}]";
            Debugger::log($message, Debugger::EXCEPTION);
            return new JsonApiResponse(
                Response::S500_INTERNAL_SERVER_ERROR,
                ['status' => 'error', 'message' => $message]
            );
        }

        return new JsonApiResponse(Response::S201_CREATED, $result);
    }
}
