<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Nette\Http\Response;

class ScenariosElementApiHandler extends ApiHandler
{
    private $elementsRepository;

    private $scenariosRepository;

    public function __construct(
        ElementsRepository $elementsRepository,
        ScenariosRepository $scenariosRepository
    ) {
        $this->elementsRepository = $elementsRepository;
        $this->scenariosRepository = $scenariosRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'scenario_id', InputParam::REQUIRED),
            new InputParam(InputParam::TYPE_GET, 'element_uuid', InputParam::REQUIRED),
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
            $scenario = $this->scenariosRepository->getScenario((int)$params['scenario_id']);
        } catch (\Exception $exception) {
            $response = new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()]);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
            return $response;
        }

        if (!$scenario) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Scenario with ID [{$params['scenario_id']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $element = $this->elementsRepository->findByScenarioIDAndElementUUID((int)$params['scenario_id'], $params['element_uuid']);
        if (!$element) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Element with UIID [{$params['element_uuid']}] for scenario [{$params['scenario_id']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        // TODO: temp returned HTML tooltip, this will be dynamically generated
        $html = <<<HTML
<style>
  #tooltip th {
    width: 150px;
    text-align: right;
    padding-right: 1em;
  }
</style>

<div id="tooltip">
  <h1 style="color: red;">Tooltip</h1>
  <p>With some description...</p>
  
  <table>
    <caption>...and statistics</caption>
    <tr>
      <th>Entered</th>
      <td>1000</td>
    </tr>
    <tr>
			<th>Matched</th>
      <td>200</td>
    </tr>
    <tr>
			<th>Not matched</th>
      <td>800</td>
		</tr>
  </table>
</div>
HTML;

        $response = new JsonResponse(['html' => $html]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
