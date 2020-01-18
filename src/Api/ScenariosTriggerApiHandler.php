<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Latte\Engine;
use Nette\Http\Response;

class ScenariosTriggerApiHandler extends ApiHandler
{
    private $triggersRepository;

    private $triggerStatsRepository;

    public function __construct(TriggersRepository $triggersRepository, TriggerStatsRepository $triggerStatsRepository)
    {
        $this->triggersRepository = $triggersRepository;
        $this->triggerStatsRepository = $triggerStatsRepository;
    }

    public function params()
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'trigger_uuid', InputParam::REQUIRED),
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

        $trigger = $this->triggersRepository->findByUuid($params['trigger_uuid']);
        if (!$trigger) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Trigger with UIID [{$params['trigger_uuid']}] not found."]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $response = new JsonResponse(['html' => $this->renderTooltip($trigger->id)]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function renderTooltip($triggerId): string
    {
        $stats = $this->triggerStatsRepository->countsFor($triggerId);

        $engine = new Engine();
        $templateFile = __DIR__ . '/../templates/builder/triggerTooltip.latte';

        $template = $engine->renderToString(
            $templateFile,
            [
                'started' => $stats[JobsRepository::STATE_CREATED] ?? 0,
                'finished' => $stats[JobsRepository::STATE_FINISHED] ?? 0,
            ]
        );

        return $template;
    }
}
