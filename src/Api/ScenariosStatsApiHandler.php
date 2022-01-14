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
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Nette\Database\Table\IRow;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class ScenariosStatsApiHandler extends ApiHandler
{
    private const DAY = "24h";
    private const MONTH = "30d";

    private $scenariosRepository;

    private $elementStatsRepository;

    private $triggerStatsRepository;

    private $jobsRepository;

    public function __construct(
        ScenariosRepository $scenariosRepository,
        ElementStatsRepository $elementStatsRepository,
        TriggerStatsRepository $triggerStatsRepository,
        JobsRepository $jobsRepository
    ) {
        $this->scenariosRepository = $scenariosRepository;
        $this->elementStatsRepository = $elementStatsRepository;
        $this->triggerStatsRepository = $triggerStatsRepository;
        $this->jobsRepository = $jobsRepository;
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

        $scenarioRow = $this->scenariosRepository->find((int)$params['id']);

        if (!$scenarioRow) {
            $response = new JsonResponse([
                'status' => 'error',
                'message' => "Scenario with ID [{$params['id']}] not found."
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        $statistics = $this->getTriggerStatistics($scenarioRow) + $this->getElementsStatistics($scenarioRow);

        $response = new JsonResponse(['statistics' => $statistics]);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }

    private function getTriggerStatistics(IRow $scenarioRow): array
    {
        $relatedTriggers = $scenarioRow->related('scenarios_triggers')
            ->where('deleted_at IS NULL')
            ->fetchPairs('id', 'uuid');

        $relatedTriggerIds = array_keys($relatedTriggers);
        $triggerStatsDay = $this->triggerStatsRepository->countsForTriggers($relatedTriggerIds, new DateTime('- 24 hours'));
        $triggerStatsMonth = $this->triggerStatsRepository->countsForTriggers($relatedTriggerIds, new DateTime('- 30 days'));

        $result = [];
        foreach ($relatedTriggers as $triggerId => $triggerUuid) {
            $result[$triggerUuid] = [
                "finished" => [
                    "24h" => (int)($triggerStatsDay[$triggerId][JobsRepository::STATE_FINISHED] ?? 0),
                    "30d" => (int)($triggerStatsMonth[$triggerId][JobsRepository::STATE_FINISHED] ?? 0),
                ]
            ];
        }

        return $result;
    }

    private function getElementsStatistics(IRow $scenarioRow): array
    {
        $relatedElements = $scenarioRow->related('scenarios_elements')
            ->where('deleted_at IS NULL')
            ->fetchPairs('id');

        $relatedElementsIds = array_keys($relatedElements);
        $elementStatsDay = $this->elementStatsRepository->countsForElements($relatedElementsIds, new DateTime('- 24 hours'));
        $elementStatsMonth = $this->elementStatsRepository->countsForElements($relatedElementsIds, new DateTime('- 30 days'));

        $jobStats = $this->jobsRepository->getCountForElementsAndState($relatedElementsIds);

        $result = [];
        foreach ($relatedElements as $elementId => $elementRow) {
            switch ($elementRow->type) {
                case ElementsRepository::ELEMENT_TYPE_CONDITION:
                case ElementsRepository::ELEMENT_TYPE_SEGMENT:
                    $result[$elementRow->uuid] = [
                        "matched" => [
                            self::DAY => $elementStatsDay[$elementId][ElementStatsRepository::STATE_POSITIVE] ?? 0,
                            self::MONTH => $elementStatsMonth[$elementId][ElementStatsRepository::STATE_POSITIVE] ?? 0,
                        ],
                        "notMatched" => [
                            self::DAY => $elementStatsDay[$elementId][ElementStatsRepository::STATE_NEGATIVE] ?? 0,
                            self::MONTH => $elementStatsMonth[$elementId][ElementStatsRepository::STATE_NEGATIVE] ?? 0,
                        ]
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_GOAL:
                    $result[$elementRow->uuid] = [
                        "completed" => [
                            self::DAY => $elementStatsDay[$elementId][ElementStatsRepository::STATE_POSITIVE] ?? 0,
                            self::MONTH => $elementStatsMonth[$elementId][ElementStatsRepository::STATE_POSITIVE] ?? 0,
                        ],
                        "timeout" => [
                            self::DAY => $elementStatsDay[$elementId][ElementStatsRepository::STATE_NEGATIVE] ?? 0,
                            self::MONTH => $elementStatsMonth[$elementId][ElementStatsRepository::STATE_NEGATIVE] ?? 0,
                        ],
                        "recheck" => [
                            $jobStats[$elementId][JobsRepository::STATE_SCHEDULED] ?? 0,
                        ],
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_WAIT:
                    $result[$elementRow->uuid] = [
                        "finished" => [
                            self::DAY => $elementStatsDay[$elementId][ElementStatsRepository::STATE_FINISHED] ?? 0,
                            self::MONTH => $elementStatsMonth[$elementId][ElementStatsRepository::STATE_FINISHED] ?? 0,
                        ],
                        "waiting" => [
                            $jobStats[$elementId][JobsRepository::STATE_STARTED] ?? 0,
                        ],
                    ];
                    break;
                case ElementsRepository::ELEMENT_TYPE_ABTEST:
                    $options = Json::decode($elementRow->options, Json::FORCE_ARRAY);
                    foreach ($options['variants'] as $variant) {
                        $result[$elementRow->uuid][$variant['code']][self::MONTH] = $elementStatsMonth[$elementId][$variant['code']] ?? 0;
                        $result[$elementRow->uuid][$variant['code']][self::DAY] = $elementStatsDay[$elementId][$variant['code']] ?? 0;
                    }
                    break;
                case ElementsRepository::ELEMENT_TYPE_EMAIL:
                case ElementsRepository::ELEMENT_TYPE_BANNER:
                case ElementsRepository::ELEMENT_TYPE_GENERIC:
                case ElementsRepository::ELEMENT_TYPE_PUSH_NOTIFICATION:
                    $result[$elementRow->uuid] = [
                        "finished" => [
                            self::DAY => $elementStatsDay[$elementId][ElementStatsRepository::STATE_FINISHED] ?? 0,
                            self::MONTH => $elementStatsMonth[$elementId][ElementStatsRepository::STATE_FINISHED] ?? 0,
                        ]
                    ];
                    break;
                default:
                    break;
            }
        }

        return $result;
    }
}
