<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Engine\GraphConfiguration;
use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;

class GraphConfigurationTest extends BaseTestCase
{
    /** @var GraphConfiguration */
    private $graph;

    public function setUp(): void
    {
        parent::setUp();
        $this->graph = $this->inject(GraphConfiguration::class);
    }

    public function testPaths()
    {
        $scenarioRepository = $this->getRepository(ScenariosRepository::class);
        $scenarioRepository->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_created'],
                    'elements' => ['element_wait']
                ])
            ],
            'elements' => [
                self::obj([
                    'name' => '',
                    'id' => 'element_wait',
                    'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
                    'wait' => [
                        'minutes' => 10,
                        'descendants' => [
                            ['uuid' => 'element_segment']
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_segment',
                    'type' => ElementsRepository::ELEMENT_TYPE_SEGMENT,
                    'segment' => [
                        'code' => 'TESTSEGMENT',
                        'descendants' => [
                            ['uuid' => 'element_email1', 'direction' => 'positive'],
                            ['uuid' => 'element_email2', 'direction' => 'negative'],
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email1',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'TESTEMAIL']
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email2',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'TESTEMAIL']
                ])
            ]
        ]);

        $this->graph->reload();

        $this->assertEquals(
            [$this->elementId('element_wait')],
            $this->graph->triggerDescendants($this->triggerId('trigger1'))
        );

        $this->assertEquals(
            [$this->elementId('element_segment')],
            $this->graph->elementDescendants($this->elementId('element_wait'))
        );

        $this->assertEquals(
            [$this->elementId('element_email1')],
            $this->graph->elementDescendants($this->elementId('element_segment'))
        );

        $this->assertEquals(
            [$this->elementId('element_email2')],
            $this->graph->elementDescendants($this->elementId('element_segment'), false)
        );

        $this->assertEmpty($this->graph->elementDescendants($this->elementId('element_email1')));
    }
}
