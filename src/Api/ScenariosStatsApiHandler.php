<?php

namespace Crm\ScenariosModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Params\InputParam;
use Crm\ScenariosModule\Repositories\ElementStatsRepository;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggerStatsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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
        parent::__construct();

        $this->scenariosRepository = $scenariosRepository;
        $this->elementStatsRepository = $elementStatsRepository;
        $this->triggerStatsRepository = $triggerStatsRepository;
        $this->jobsRepository = $jobsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(InputParam::TYPE_GET, 'id', InputParam::REQUIRED),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $scenarioRow = $this->scenariosRepository->find((int)$params['id']);

        if (!$scenarioRow) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'status' => 'error',
                'message' => "Scenario with ID [{$params['id']}] not found."
            ]);
            return $response;
        }

        $statistics = $this->getTriggerStatistics($scenarioRow) + $this->getElementsStatistics($scenarioRow);

        $response = new JsonApiResponse(Response::S200_OK, ['statistics' => $statistics]);
        return $response;
    }

    private function getTriggerStatistics(ActiveRow $scenarioRow): array
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

    private function getElementsStatistics(ActiveRow $scenarioRow): array
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
