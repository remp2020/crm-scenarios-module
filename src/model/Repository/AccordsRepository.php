<?php

namespace Crm\ScenariosModule\Repository;

use Crm\ApplicationModule\Repository;
use Nette\Application\BadRequestException;
use Nette\Caching\IStorage;
use Nette\Database\Context;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

class AccordsRepository extends Repository
{
    protected $tableName = 'scenarios_accords';

    private $accordTriggersRepository;

    private $accordTriggerElementsRepository;

    private $elementsRepository;

    private $elementElementsRepository;

    private $eventsRepository;

    public function __construct(
        Context $database,
        IStorage $cacheStorage = null,
        AccordTriggersRepository $accordTriggersRepository,
        AccordTriggerElementsRepository $accordTriggerElementsRepository,
        ElementsRepository $elementsRepository,
        ElementElementsRepository $elementElementsRepository,
        EventsRepository $eventsRepository
    ) {
        parent::__construct($database, $cacheStorage);

        $this->accordTriggersRepository = $accordTriggersRepository;
        $this->accordTriggerElementsRepository = $accordTriggerElementsRepository;
        $this->elementsRepository = $elementsRepository;
        $this->elementElementsRepository = $elementElementsRepository;
        $this->eventsRepository = $eventsRepository;
    }

    /**
     * @param array $data
     * @return false|int - Returns false when accordID provided for update is not found
     * @throws BadRequestException
     * @throws \Exception
     */
    public function createOrUpdate(array $data)
    {
        $accordData['name'] = $data['title'];
        $accordData['visual'] = json_encode($data['visual']);
        $accordData['created_at'] = new DateTime();
        $accordData['modified_at'] = new DateTime();

        // save or update accord details
        if (isset($data['id'])) {
            $accord = $this->find($data['id']);
            if (!$accord) {
                return false;
            }
            $this->update($accord, $accordData);
        } else {
            $accord = $this->insert($accordData);
            if (!$accord) {
                throw new \Exception("Unable to save accord.");
            }
        }
        $accordID = $accord->getPrimary();

        // TODO: move whole block to elements repository
        // add elements of accord
        $this->elementsRepository->removeAllByAccord($accordID);
        $elementPairs = [];
        foreach ($data['elements'] as $element) {
            $elementData = [
                'accord_id' => $accordID,
                'uuid' => $element->id,
                'name' => $element->title,
                'type' => $element->type,
            ];

            switch ($element->type) {
                case 'action':
                    // TODO: check type of action?
                    $elementData['action_code'] = $element->action->email->code;
                    $elementPairs[$element->id]['positive'] = $element->action->descendants;
                    break;
                case 'segment':
                    $elementData['segment_code'] = $element->segment->code;
                    $elementPairs[$element->id]['positive'] = $element->segment->descendants_positive;
                    $elementPairs[$element->id]['negative'] = $element->segment->descendants_negative;
                    break;
                case 'wait':
                    $elementData['wait_time'] = $element->wait->minutes;
                    $elementPairs[$element->id]['positive'] = $element->wait->descendants;
                    break;
                default:
                    throw new BadRequestException("Unknown element type [{$element->type}].");
            }

            $this->elementsRepository->insert($elementData);
        }

        // TODO: move whole block to elementElements repository
        // process elements' descendants
        foreach ($elementPairs as $parent => $descendants) {
            $parentID = $this->elementsRepository->findBy('uuid', $parent)->getPrimary();

            if (!isset($descendants['positive'])) {
                continue;
            }
            foreach ($descendants['positive'] as $descendant) {
                $descendantID = $this->elementsRepository->findBy('uuid', $descendant)->getPrimary();
                $elementElementsData = [
                    'parent_element_id' => $parentID,
                    'child_element_id' => $descendantID,
                    'positive' => 1,
                ];
                $this->elementElementsRepository->insert($elementElementsData);
            }

            if (!isset($descendants['negative'])) {
                continue;
            }
            foreach ($descendants['negative'] as $descendant) {
                $descendantID = $this->elementsRepository->findBy('uuid', $descendant)->getPrimary();
                $elementElementsData = [
                    'parent_element_id' => $parentID,
                    'child_element_id' => $descendantID,
                    'positive' => 0,
                ];
                $this->elementElementsRepository->insert($elementElementsData);
            }
        }

        // TODO: move whole block to accordTriggers repository
        // process triggers (root elements)
        $this->accordTriggersRepository->removeAllByAccordID($accordID);
        foreach ($data['triggers'] as $trigger) {
            if ($trigger->type !== 'event') {
                throw new BadRequestException("Unknown trigger type [{$trigger->type}].");
            }
            $event = $this->eventsRepository->findBy('code', $trigger->event->code);
            if (!$event) {
                throw new BadRequestException("Unknown event type [{$trigger->event->code}].");
            }
            $triggerData = [
                'accord_id' => $accordID,
                'event_id' => $event->getPrimary(),
                'uuid' => $trigger->id,
                'name' => $trigger->title,
            ];
            $accordTrigger = $this->accordTriggersRepository->insert($triggerData);

            // insert links from triggers
            foreach ($trigger->elements as $triggerElement) {
                $triggerElementID = $this->elementsRepository->findBy('uuid', $triggerElement)->getPrimary();
                $triggerElementData = [
                    'accord_trigger_id' => $accordTrigger->getPrimary(),
                    'element_id' => $triggerElementID,
                ];
            }

            $this->accordTriggerElementsRepository->insert($triggerElementData);
        }

        return $accordID;
    }


    /**
     * Load whole accord with all triggers and elements.
     *
     * @param int $accordID
     * @return array|false if accord was not found
     * @throws BadRequestException
     */
    public function getAccord(int $accordID)
    {
        $accord = $this->find($accordID);
        if (!$accord) {
            return false;
        }

        $triggers = $this->getTriggers($accord);
        if (empty($triggers)) {
            throw new BadRequestException("No triggers owned by accord with ID [{$accordID}].");
        }

        $elements = $this->getElements($accord);
        if (empty($elements)) {
            throw new BadRequestException("No elements owned by accord with ID [{$accordID}].");
        }

        $result = [
            'id' => $accord->id,
            'title' => $accord->name,
            'triggers' => $triggers,
            'elements' => $elements,
            'visual' => json_decode($accord->visual),
        ];

        return $result;
    }

    private function getTriggers(ActiveRow $accord): array
    {
        $triggers = [];
        foreach ($accord->related('scenarios_accord_triggers')->fetchAll() as $accordTrigger) {
            $trigger = [
                'id' => $accordTrigger->uuid,
                'title' => $accordTrigger->name,
                'type' => 'event',
                'event' => [
                    'code' => $accordTrigger->event->code
                ],
                'elements' => [],
            ];

            foreach ($accordTrigger->related('scenarios_accord_trigger_elements') as $triggerElement) {
                $trigger['elements'][] = $triggerElement->element->uuid;
            }

            $triggers[$accordTrigger->uuid] = $trigger;
        }

        return $triggers;
    }

    private function getElements(ActiveRow $accord): array
    {
        $elements = [];
        foreach ($accord->related('scenarios_elements')->fetchAll() as $accordElement) {
            $element = [
                'id' => $accordElement->uuid,
                'title' => $accordElement->name,
                'type' => $accordElement->type,
            ];

            $descendants = $this->getElementDescendants($accordElement);

            switch ($accordElement->type) {
                case 'action':
                    $element['action'] = [
                        'type' => 'email',
                        'email' => [
                            'code' => $accordElement->action_code,
                        ],
                    ];
                    $element['action']['descendants'] = array_merge(
                        array_keys($descendants['descendants_positive']),
                        array_keys($descendants['descendants_negative'])
                    );
                    break;
                case 'segment':
                    $element['segment'] = [
                        'code' => $accordElement->segment_code,
                    ];
                    $element['segment']['descendants_positive'] = $descendants['descendants_positive'];
                    $element['segment']['descendants_negative'] = $descendants['descendants_negative'];
                    break;
                case 'wait':
                    $element['wait'] = [
                        'minutes' => $accordElement->wait_time,
                    ];
                    $element['wait']['descendants'] = array_merge(
                        $descendants['descendants_positive'],
                        $descendants['descendants_negative']
                    );
                    break;
                default:
                    throw new \Exception("Unable to load element uuid [{$accordElement->uuid}] - unknown element type [{$accordElement->type}].");
            }

            $elements[$accordElement->uuid] = $element;
        }

        return $elements;
    }

    private function getElementDescendants(ActiveRow $element): array
    {
        $descendantsPositive = [];
        $descendantsNegative = [];
        foreach ($element->related('scenarios_element_elements.parent_element_id')->fetchAll() as $descendant) {
            $uuid = $descendant->ref('scenarios_elements', 'child_element_id')->uuid;
            if ($descendant->positive == 1 || $descendant->positive === true) {
                $descendantsPositive[] = $uuid;
            } else {
                $descendantsNegative[] = $uuid;
            }
        }
        return [
            'descendants_positive' => $descendantsPositive,
            'descendants_negative' => $descendantsNegative,
        ];
    }
}
