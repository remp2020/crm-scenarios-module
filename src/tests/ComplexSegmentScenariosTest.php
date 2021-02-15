<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Repository\ElementsRepository;
use Crm\ScenariosModule\Repository\ElementStatsRepository;
use Crm\ScenariosModule\Repository\JobsRepository;
use Crm\ScenariosModule\Repository\ScenariosRepository;
use Crm\ScenariosModule\Repository\TriggersRepository;
use Crm\ScenariosModule\Repository\TriggerStatsRepository;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\UsersModule\Auth\UserManager;
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
        $this->engine->run(true); // process trigger, finish its job and create segment job
        $this->engine->run(true); // job(wait): created -> started

        $this->dispatcher->handle(); // job(wait): started -> finished

        $this->engine->run(true); // job(wait) deleted, job(segment) created
        $this->engine->run(true); // job(segment) created -> scheduled

        $this->dispatcher->handle(); // job(segment): scheduled -> started -> finished()

        // Check user was in segment
        $segmentJob = $jr->getFinishedJobs()->fetch();
        $this->assertTrue(Json::decode($segmentJob->result)->in);

        $this->engine->run(true); // job(segment) deleted, job(email) created
        $this->engine->run(true); // job(email) created -> scheduled

        $this->dispatcher->handle(); // job(email): scheduled -> started -> finished()

        // Check email was sent
        $this->assertCount(1, $this->mailsSentTo('test@email.com'));

        $this->engine->run(true); // job(email) deleted

        $this->assertCount(0, $jr->getAllJobs()->fetchAll());

        // Check stats count

        $tsr = $this->getRepository(TriggerStatsRepository::class);
        $esr = $this->getRepository(ElementStatsRepository::class);

        $triggerStats = $tsr->countsFor($this->triggerId('trigger1'));
        // Triggers are only CREATED and then FINISHED
        $this->assertEquals($triggerStats[JobsRepository::STATE_CREATED], 1);
        $this->assertEquals($triggerStats[JobsRepository::STATE_FINISHED], 1);

        $waitStats = $esr->countsFor($this->elementId('element_wait'));
        foreach (JobsRepository::allStates() as $state) {
            $count = $waitStats[$state] ?? 0;

            // WAIT is a special element, it's directly STARTED from CREATED state (SCHEDULED is skipped)
            if ($state === JobsRepository::STATE_SCHEDULED || $state === JobsRepository::STATE_FAILED) {
                $this->assertEquals(0, $count);
            } else {
                $this->assertEquals(1, $count);
            }
        }

        $segmentStats = $esr->countsFor($this->elementId('element_segment'));
        foreach (JobsRepository::allStates() as $state) {
            $count = $segmentStats[$state] ?? 0;

            if ($state == JobsRepository::STATE_FAILED) {
                $this->assertEquals(0, $count);
            } else {
                $this->assertEquals(1, $count);
            }
        }

        $emailStats = $esr->countsFor($this->elementId('element_email'));
        foreach (JobsRepository::allStates() as $state) {
            $count = $emailStats[$state] ?? 0;

            if ($state == JobsRepository::STATE_FAILED) {
                $this->assertEquals(0, $count);
            } else {
                $this->assertEquals(1, $count);
            }
        }
    }
}
