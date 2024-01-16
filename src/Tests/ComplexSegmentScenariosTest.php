<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repositories\ElementStatsRepository;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggerStatsRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class ComplexSegmentScenariosTest extends BaseTestCase
{
    /**
     * Test scenario with TRIGGER -> WAIT -> SEGMENT -> MAIL flow
     * + Stats are checked
     */
    public function testWaitSegmentMailScenario()
    {
        $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'test1',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_EVENT,
                    'id' => 'trigger1',
                    'event' => ['code' => 'user_registered'],
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
                        'code' => 'tests_all_users',
                        'descendants' => [
                            ['uuid' => 'element_email', 'direction' => 'positive']
                        ]
                    ]
                ]),
                self::obj([
                    'name' => '',
                    'id' => 'element_email',
                    'type' => ElementsRepository::ELEMENT_TYPE_EMAIL,
                    'email' => ['code' => 'empty_template_code']
                ]),
            ]
        ]);
        $jr = $this->getRepository(JobsRepository::class);

        // Insert segment containing all users
        $segmentGroup = $this->getRepository(SegmentGroupsRepository::class)->add('Test group', 'test_group');
        $this->getRepository(SegmentsRepository::class)->add(
            'All users (tests)',
            1,
            'tests_all_users',
            'users',
            'users.id',
            'SELECT %fields% FROM %table% WHERE %where%',
            $segmentGroup
        );

        // Add user, which triggers scenario
        $this->inject(UserManager::class)->addNewUser('test@email.com', false, 'unknown', null, false);

        // Simulate running of Hermes + Scenarios Engine
        $this->dispatcher->handle(); // run Hermes to create trigger job
        $this->engine->run(3); // // process trigger, finish its job and create+schedule segment job
        $this->dispatcher->handle(); // job(wait): started -> finished
        $this->engine->run(2); // // job(wait) deleted, job(segment) created+scheduled
        $this->dispatcher->handle(); // job(segment): scheduled -> started -> finished()

        // Check user was in segment
        $segmentJob = $jr->getFinishedJobs()->fetch();
        $this->assertTrue(Json::decode($segmentJob->result)->in);

        $this->engine->run(3); // job(segment) deleted, job(email) created+scheduled

        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));

        $this->engine->run(1); // job(email) deleted

        $this->assertCount(0, $jr->getAllJobs()->fetchAll());

        // Check stats count

        /** @var TriggerStatsRepository $tsr */
        $tsr = $this->getRepository(TriggerStatsRepository::class);

        /** @var ElementStatsRepository $esr */
        $esr = $this->getRepository(ElementStatsRepository::class);

        $triggerStats = $tsr->countsForTriggers([$this->triggerId('trigger1')], new DateTime('-1 hour'));
        // Triggers are only CREATED and then FINISHED
        $this->assertEquals($triggerStats[$this->triggerId('trigger1')][JobsRepository::STATE_FINISHED], 1);

        $elementStats = $esr->countsForElements([
            $this->elementId('element_wait'),
            $this->elementId('element_segment'),
            $this->elementId('element_email')
        ], new DateTime('-1 hour'));

        $this->assertEquals(1, sizeof($elementStats[$this->elementId('element_wait')]));
        $this->assertEquals(1, $elementStats[$this->elementId('element_wait')][JobsRepository::STATE_FINISHED]);

        $this->assertEquals(1, sizeof($elementStats[$this->elementId('element_segment')]));
        $this->assertEquals(1, $elementStats[$this->elementId('element_segment')][ElementStatsRepository::STATE_POSITIVE]);

        $this->assertEquals(1, sizeof($elementStats[$this->elementId('element_email')]));
        $this->assertEquals(1, $elementStats[$this->elementId('element_email')][JobsRepository::STATE_FINISHED]);
    }
}
