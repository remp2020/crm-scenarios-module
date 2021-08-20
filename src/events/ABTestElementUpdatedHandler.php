<?php

namespace Crm\ScenariosModule\Events;

use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Seeders\SegmentGroupsSeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Database\Table\IRow;
use Nette\Utils\Json;

class ABTestElementUpdatedHandler extends AbstractListener
{
    private $segmentsRepository;

    private $segmentGroupsRepository;

    private $elementsRepository;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentGroupsRepository $segmentGroupsRepository,
        ElementsRepository $elementsRepository
    ) {
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentGroupsRepository = $segmentGroupsRepository;
        $this->elementsRepository = $elementsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof AbTestElementUpdatedEvent) {
            throw new \Exception('unexpected type of event, ABTestElementCreatedEvent expected: ' . get_class($event));
        }

        $elementRow = $event->getElement();
        if (!$elementRow) {
            throw new \Exception('ABTestElementCreatedEvent without scenario element');
        }

        if ($elementRow->type !== ElementsRepository::ELEMENT_TYPE_ABTEST) {
            throw new \Exception("Wrong scenario element type: {$elementRow->type}");
        }

        $group = $this->segmentGroupsRepository->findByCode(SegmentGroupsSeeder::AB_TEST_SEGMENT_GROUP_CODE);
        if ($group === null) {
            throw new \Exception("Segments group [" . SegmentGroupsSeeder::AB_TEST_SEGMENT_GROUP_CODE . "] does not exist. Cannot add segment.");
        }

        foreach ($event->getSegments() as $segment) {
            if ($segment['id']) {
                $segmentRow = $this->segmentsRepository->findById($segment['id']);

                if ($segmentRow) {
                    $this->segmentsRepository->update($segmentRow, ['name' => $segment['name']]);
                    continue;
                }
            }

            $segmentProperties = $this->prepareSegmentProperties($elementRow, $segment['uuid'], $segment['name']);
            $segmentRow = $this->segmentsRepository->upsert(
                $segmentProperties['code'],
                $segmentProperties['name'],
                $segmentProperties['query_string'],
                $segmentProperties['table_name'],
                $segmentProperties['fields'],
                $group
            );

            $this->updateSegmentReferenceInElementOptions($elementRow, $segment['uuid'], $segmentRow);
        }
    }

    private function prepareSegmentProperties(IRow $elementRow, string $code, string $name): array
    {
        return [
            'name' => $name,
            'code' => SegmentGroupsSeeder::getSegmentCode($elementRow, $code),
            'version' => 1,
            'table_name' => 'users',
            'fields' => 'users.id, users.email',
            'query_string' => $this->getQueryString($elementRow->id, $code)
        ];
    }

    private function getQueryString(int $elementId, string $variantCode): string
    {
        $query = <<<SQL
SELECT %fields% FROM %table%
INNER JOIN `scenarios_selected_variants`
    ON `scenarios_selected_variants`.`user_id`=%table%.`id`
WHERE
    %where%
    AND %table%.`active` = 1
    AND `scenarios_selected_variants`.`element_id` = {$elementId}
    AND `scenarios_selected_variants`.`variant_code` = '{$variantCode}'
GROUP BY %table%.`id`
SQL;

        return $query;
    }

    private function updateSegmentReferenceInElementOptions(IRow $elementRow, string $code, IRow $segmentRow): void
    {
        $elementRow = $this->elementsRepository->find($elementRow->id);

        $options = Json::decode($elementRow->options, Json::FORCE_ARRAY);
        foreach ($options['variants'] as $index => $variant) {
            if ($variant['code'] === $code) {
                $options['variants'][$index]['segment_id'] = $segmentRow->id;
            }
        }
        $this->elementsRepository->update($elementRow, ['options' => Json::encode($options)]);
    }
}
