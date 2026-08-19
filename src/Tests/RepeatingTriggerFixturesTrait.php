<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTrigger;
use Crm\ScenariosModule\Repositories\ElementsRepository;
use Crm\ScenariosModule\Repositories\ScenariosRepository;
use Crm\ScenariosModule\Repositories\TriggersRepository;
use Crm\SegmentModule\Repositories\SegmentGroupsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\UsersModule\Models\Auth\UserManager;

/**
 * Fixtures shared by the tests of the repeating trigger: a scenario built around one repeating trigger
 * node, the segment it enrolls and the users of that segment.
 */
trait RepeatingTriggerFixturesTrait
{
    private function addRepeatingScenario(array $options = [])
    {
        $options += [
            'segment_code' => 'tests_all_users',
            'frequency' => 'weekly',
            'day' => 1,
            'time' => '09:00',
        ];

        return $this->getRepository(ScenariosRepository::class)->createOrUpdate([
            'name' => 'repeating scenario',
            'enabled' => true,
            'triggers' => [
                self::obj([
                    'name' => '',
                    'type' => TriggersRepository::TRIGGER_TYPE_REPEATING,
                    'id' => 'trigger_repeating',
                    'event' => ['code' => RepeatingTrigger::EVENT_CODE],
                    'options' => $options,
                    'elements' => ['element_wait'],
                ]),
            ],
            'elements' => [$this->waitElement('element_wait')],
        ]);
    }

    private function waitElement(string $uuid)
    {
        return self::obj([
            'name' => '',
            'id' => $uuid,
            'type' => ElementsRepository::ELEMENT_TYPE_WAIT,
            'wait' => ['minutes' => 10],
        ]);
    }

    private function addUsersSegment(string $code, string $groupCode = 'test_group'): void
    {
        $segmentGroup = $this->getRepository(SegmentGroupsRepository::class)->add('Test group', $groupCode);
        $this->getRepository(SegmentsRepository::class)->add(
            'All users (' . $code . ')',
            1,
            $code,
            'users',
            'users.id',
            'SELECT %fields% FROM %table% WHERE %where%',
            $segmentGroup,
        );
    }

    /**
     * @return int[] IDs of the created users, ascending
     */
    private function addUsers(int $count): array
    {
        $userManager = $this->inject(UserManager::class);

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $userManager->addNewUser("test{$i}@email.com", false, 'unknown', null, false)->id;
        }

        return $ids;
    }
}
