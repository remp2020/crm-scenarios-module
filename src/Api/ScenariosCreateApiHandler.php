<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\ScenariosModule\Engine\Validator\ScenarioValidationException;
use Crm\ScenariosModule\Engine\Validator\ScenarioValidator;
use Crm\ScenariosModule\Repositories\ScenarioInvalidDataException;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Nette\Http\IResponse;
use Nette\Http\Request;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;

class ScenariosCreateApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    public function __construct(
        private readonly Request $request,
        private readonly ScenariosRepository $scenariosRepository,
        private readonly ScenarioValidator $scenarioValidator,
    ) {
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
                IResponse::S403_Forbidden,
                [
                    'status' => 'error',
                    'message' => 'Cannot authorize user',
                    'code' => 'unauthorized_user',
                ],
            );
        }

        $contentType = $this->request->getHeader('Content-Type');

        // check if Content Type header contains `application/json`
        $contentTypes = explode(';', $contentType);
        if (!in_array('application/json', array_map('trim', $contentTypes), true)) {
            return new JsonApiResponse(
                IResponse::S400_BadRequest,
                [
                    'status' => 'error',
                    'message' => "Incorrect Content-Type [{$contentType}]. Expected 'application/json'.",
                    'code' => 'invalid_content_type',
                ],
            );
        }

        $requestValidationResult = $this->validateInput(
            __DIR__ . '/scenarios-create-request.schema.json',
            $this->rawPayload(),
        );

        if ($requestValidationResult->hasErrorResponse()) {
            return $requestValidationResult->getErrorResponse();
        }

        try {
            $this->scenarioValidator->validate($requestValidationResult->getParsedObjectAsArray());
        } catch (ScenarioValidationException $exception) {
            return new JsonApiResponse(
                IResponse::S422_UnprocessableEntity,
                [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                    'code' => 'validation_error',
                    'affected_trigger_id' => $exception->triggerId,
                    'affected_element_id' => $exception->elementId,
                ],
            );
        }

        $data = (array)$requestValidationResult->getParsedObject();
        try {
            $scenario = $this->scenariosRepository->createOrUpdate($data);
        } catch (ScenarioInvalidDataException $exception) {
            return new JsonApiResponse(
                IResponse::S409_Conflict,
                [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                    'code' => 'invalid_data',
                ],
            );
        } catch (\Exception $exception) {
            Debugger::log($exception, Debugger::EXCEPTION);
            return new JsonApiResponse(
                IResponse::S500_InternalServerError,
                [
                    'status' => 'error',
                    'message' => $exception->getMessage(),
                    'code' => 'unknown_error',
                ],
            );
        }

        if (!$scenario) {
            return new JsonApiResponse(
                IResponse::S404_NotFound,
                [
                    'status' => 'error',
                    'message' => "Scenario with provided ID [{$data['id']}] not found.",
                    'code' => 'not_found',
                ],
            );
        }

        $result = $this->scenariosRepository->getScenario($scenario->id);
        if (!$result) {
            // any error at this moment means there is issue on our side
            $message = "Unable to load scenario with ID [{$scenario->id}]";
            Debugger::log($message, Debugger::EXCEPTION);
            return new JsonApiResponse(
                IResponse::S500_InternalServerError,
                [
                    'status' => 'error',
                    'message' => $message,
                    'code' => 'not_found',
                ],
            );
        }

        return new JsonApiResponse(IResponse::S201_Created, $result);
    }
}
