<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\ScenarioInvalidDataException;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\DataProvider;

class RepeatingTriggerSaveTest extends BaseTestCase
{
    private ScenariosRepository $scenariosRepository;

    private TriggersRepository $triggersRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenariosRepository = $this->getRepository(ScenariosRepository::class);
        $this->triggersRepository = $this->getRepository(TriggersRepository::class);

        $segmentGroup = $this->getRepository(SegmentGroupsRepository::class)->add('Test group', 'test_group');
        $segmentsRepository = $this->getRepository(SegmentsRepository::class);
        $segmentsRepository->add(
            'All users (tests)',
            1,
            'tests_all_users',
            'users',
            'users.id',
            'SELECT %fields% FROM %table% WHERE %where%',
            $segmentGroup,
        );
        $segmentsRepository->add(
            'All subscriptions (tests)',
            1,
            'tests_all_subscriptions',
            'subscriptions',
            'subscriptions.id',
            'SELECT %fields% FROM %table% WHERE %where%',
            $segmentGroup,
        );
    }

    #[DataProvider('validOptionsProvider')]
    public function testSavesRepeatingTriggerSchedule(array $options, array $expected)
    {
        $scenario = $this->createScenario($options);

        $trigger = $this->triggersRepository->findByScenarioIdAndTriggerUuid($scenario->id, 'trigger_repeating');

        $this->assertSame(TriggersRepository::TRIGGER_TYPE_REPEATING, $trigger->type);
        $this->assertSame(RepeatingTrigger::EVENT_CODE, $trigger->event_code);
        // assertEquals, not assertSame: `options` is a MySQL json column, which normalizes the key order
        // of the stored object.
        $this->assertEquals(
            ['segment_code' => 'tests_all_users'] + $expected,
            Json::decode($trigger->options, Json::FORCE_ARRAY),
        );
    }

    #[DataProvider('validOptionsProvider')]
    public function testRepeatingTriggerOptionsSurviveReadBack(array $options, array $expected)
    {
        $scenario = $this->createScenario($options);

        $readBack = $this->scenariosRepository->getScenario($scenario->id);
        $trigger = $readBack['triggers']['trigger_repeating'];

        $this->assertSame(TriggersRepository::TRIGGER_TYPE_REPEATING, $trigger['type']);
        $this->assertSame(RepeatingTrigger::EVENT_CODE, $trigger['event']['code']);
        $this->assertSame(['segment_code' => 'tests_all_users'] + $expected, $trigger['options']);
    }

    public static function validOptionsProvider(): array
    {
        return [
            'daily' => [
                ['frequency' => 'daily', 'time' => '09:00'],
                ['frequency' => 'daily', 'time' => '09:00'],
            ],
            // A day sent along with the daily frequency is meaningless and gets dropped.
            'daily drops a stray day' => [
                ['frequency' => 'daily', 'day' => 4, 'time' => '09:00'],
                ['frequency' => 'daily', 'time' => '09:00'],
            ],
            'weekly' => [
                ['frequency' => 'weekly', 'day' => 4, 'time' => '09:00'],
                ['frequency' => 'weekly', 'day' => 4, 'time' => '09:00'],
            ],
            'monthly' => [
                ['frequency' => 'monthly', 'day' => 15, 'time' => '23:59'],
                ['frequency' => 'monthly', 'day' => 15, 'time' => '23:59'],
            ],
            'monthly last day' => [
                ['frequency' => 'monthly', 'day' => 31, 'time' => '00:00'],
                ['frequency' => 'monthly', 'day' => 31, 'time' => '00:00'],
            ],
        ];
    }

    public function testEventTriggerOptionsAreUnaffected()
    {
        $scenario = $this->scenariosRepository->createOrUpdate([
            'name' => 'before event scenario',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_BEFORE_EVENT,
                    'id' => 'trigger_before',
                    'event' => ['code' => 'subscription_ends'],
                    'options' => ['minutes' => 1440],
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [$this->waitElement()],
        ]);

        $readBack = $this->scenariosRepository->getScenario($scenario->id);

        $this->assertSame(['minutes' => 1440], $readBack['triggers']['trigger_before']['options']);
    }

    public function testMissingSegmentCodeIsRejected()
    {
        $this->expectException(ScenarioInvalidDataException::class);
        $this->expectExceptionMessage("Missing 'segment_code' option for the repeating Trigger node.");

        $this->createScenario(['frequency' => 'daily', 'time' => '09:00'], null);
    }

    public function testUnknownSegmentCodeIsRejected()
    {
        $this->expectException(ScenarioInvalidDataException::class);
        $this->expectExceptionMessage('Unknown segment code [no_such_segment].');

        $this->createScenario(['frequency' => 'daily', 'time' => '09:00'], 'no_such_segment');
    }

    public function testSegmentNotBasedOnUsersTableIsRejected()
    {
        $this->expectException(ScenarioInvalidDataException::class);
        $this->expectExceptionMessage("has to be based on the 'users' table, 'subscriptions' given");

        $this->createScenario(['frequency' => 'daily', 'time' => '09:00'], 'tests_all_subscriptions');
    }

    #[DataProvider('invalidOptionsProvider')]
    public function testInvalidOptionsAreRejected(?array $options, string $expectedMessagePart)
    {
        $this->expectException(ScenarioInvalidDataException::class);
        $this->expectExceptionMessage($expectedMessagePart);

        $this->createScenario($options);
    }

    public static function invalidOptionsProvider(): array
    {
        return [
            'missing options' => [null, "Missing 'segment_code' option"],
            'missing frequency' => [['time' => '09:00'], "Missing 'frequency' option"],
            'unknown frequency' => [['frequency' => 'yearly', 'time' => '09:00'], 'Unknown repeating trigger frequency [yearly]'],

            'weekly without a day' => [['frequency' => 'weekly', 'time' => '09:00'], "Missing 'day' option for a [weekly]"],
            'weekly day zero' => [['frequency' => 'weekly', 'day' => 0, 'time' => '09:00'], 'out of range, expected 1-7'],
            'weekly day eight' => [['frequency' => 'weekly', 'day' => 8, 'time' => '09:00'], 'out of range, expected 1-7'],
            'weekly day not an integer' => [['frequency' => 'weekly', 'day' => 'monday', 'time' => '09:00'], 'is not an integer'],

            'monthly without a day' => [['frequency' => 'monthly', 'time' => '09:00'], "Missing 'day' option for a [monthly]"],
            'monthly day zero' => [['frequency' => 'monthly', 'day' => 0, 'time' => '09:00'], 'out of range, expected 1-31'],
            'monthly day 32' => [['frequency' => 'monthly', 'day' => 32, 'time' => '09:00'], 'out of range, expected 1-31'],

            'missing time' => [['frequency' => 'daily'], "Missing 'time' option"],
            'unpadded time' => [['frequency' => 'daily', 'time' => '9:00'], "is not a valid 'HH:MM' wall-clock time"],
            'hour out of range' => [['frequency' => 'daily', 'time' => '24:00'], "is not a valid 'HH:MM' wall-clock time"],
            'minute out of range' => [['frequency' => 'daily', 'time' => '09:60'], "is not a valid 'HH:MM' wall-clock time"],
        ];
    }

    /**
     * @param string|null $segmentCode NULL leaves the segment out of the options entirely
     */
    private function createScenario(?array $options, ?string $segmentCode = 'tests_all_users')
    {
        $trigger = [
            'name' => '',
            'type' => TriggersRepository::TRIGGER_TYPE_REPEATING,
            'id' => 'trigger_repeating',
            'event' => ['code' => RepeatingTrigger::EVENT_CODE],
            'elements' => ['element_wait'],
        ];
        if ($options !== null) {
            $trigger['options'] = $segmentCode === null ? $options : ['segment_code' => $segmentCode] + $options;
        }

        return $this->scenariosRepository->createOrUpdate([
            'name' => 'repeating scenario',
            'enabled' => true,
            'triggers' => [self::obj($trigger)],
            'elements' => [$this->waitElement()],
        ]);
    }

    private function waitElement()
    {
        return self::obj([
            'name' => '',
            'id' => 'element_wait',
            'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
            'wait' => ['minutes' => 10],
        ]);
    }
}
