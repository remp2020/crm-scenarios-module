<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ScenariosModule\Repository\ElementStatsRepository;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Latte\Engine;
use Nette\Http\Response;

class ScenariosElementApiHandler extends ApiHandler
{
    private $elementsRepository;

    private $elementStatsRepository;

    public function __construct(
        ElementsRepository $elementsRepository,
        ElementStatsRepository $elementStatsRepository
    ) {
        $this->elementsRepository = $elementsRepository;
        $this->elementStatsRepository = $elementStatsRepository;
    }

    public function params()
    {
        return [
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

        $element = $this->elementsRepository->findByUuid($params['element_uuid']);
        if (!$element) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Element with UIID [{$params['element_uuid']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $response = new JsonResponse(['html' => $this->renderTooltip($element->id)]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function renderTooltip($elementId): string
    {
        $stats = $this->elementStatsRepository->countsFor($elementId);

        $engine = new Engine();
        $templateFile = __DIR__ . '/../templates/builder/elementTooltip.latte';

        $template = $engine->renderToString(
            $templateFile,
            [
                'started' => $stats[JobsRepository::STATE_CREATED] ?? 0,
                'finished' => $stats[JobsRepository::STATE_FINISHED] ?? 0,
                'failed' => $stats[JobsRepository::STATE_FAILED] ?? 0,
            ]
        );

        return $template;
    }
}
