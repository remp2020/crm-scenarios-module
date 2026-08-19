<?php
declare(strict_types=1);

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Commands\RepeatingTriggerGeneratorCommand;
use Crm\ScenariosModule\Models\RepeatingTrigger\RepeatingTriggerScheduleStateEnum;
use Crm\ScenariosModule\Repositories\JobsRepository;
use Crm\ScenariosModule\Repositories\RepeatingTriggerScheduleRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The command is a thin wrapper over RepeatingTriggerGenerator, these tests cover its options: how they are
 * validated and which of the generator's two windows each of them drives.
 */
class RepeatingTriggerGeneratorCommandTest extends BaseTestCase
{
    use RepeatingTriggerFixturesTrait;

    /** 2026-08-03 is a Monday. */
    private const MONDAY_MORNING = '2026-08-03 09:05:00';

    private JobsRepository $jobsRepository;

    private RepeatingTriggerScheduleRepository $scheduleRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jobsRepository = $this->getRepository(JobsRepository::class);
        $this->scheduleRepository = $this->getRepository(RepeatingTriggerScheduleRepository::class);

        $this->addUsersSegment('tests_all_users');
    }

    public function testCommandEnrollsSegmentUsers()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $command = $this->inject(RepeatingTriggerGeneratorCommand::class);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['--now' => self::MONDAY_MORNING]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('3 users', $tester->getDisplay());
        $this->assertSame(3, $this->jobsRepository->getAllJobs()->count('*'));
    }

    public function testCommandRejectsInvalidWindowMinutes()
    {
        $command = $this->inject(RepeatingTriggerGeneratorCommand::class);

        foreach (['--catch-up-minutes', '--scan-minutes'] as $option) {
            $tester = new CommandTester($command);
            $this->assertSame(Command::FAILURE, $tester->execute([$option => 'soon']), $option);
            $this->assertStringContainsString($option, $tester->getDisplay());

            $tester = new CommandTester($command);
            $this->assertSame(Command::FAILURE, $tester->execute([$option => '0']), $option);
        }
    }

    /**
     * The two options drive two different windows, so a swap between them stays visible: with these
     * values a swap would find no occurrence at all instead of recording the skip below.
     */
    public function testCommandScanMinutesBoundsFiring()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $command = $this->inject(RepeatingTriggerGeneratorCommand::class);
        $tester = new CommandTester($command);

        // The 09:00 occurrence is 5 minutes old: still looked up, but too old to fire.
        $exitCode = $tester->execute([
            '--now' => self::MONDAY_MORNING,
            '--scan-minutes' => '3',
            '--catch-up-minutes' => '10',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));

        // Asserted before reading the row, so that "not looked up at all" fails here and not on a null.
        $this->assertSame(1, $this->scheduleRepository->getTable()->count('*'));
        $this->assertSame(
            RepeatingTriggerScheduleStateEnum::Skipped->value,
            $this->scheduleRepository->getTable()->fetch()->state,
        );
    }

    public function testCommandCatchUpMinutesBoundsLookup()
    {
        $this->addRepeatingScenario();
        $this->addUsers(3);

        $command = $this->inject(RepeatingTriggerGeneratorCommand::class);
        $tester = new CommandTester($command);

        // Looking back only 3 minutes, the 09:00 occurrence is not seen at all - not even as a skip.
        $exitCode = $tester->execute([
            '--now' => self::MONDAY_MORNING,
            '--catch-up-minutes' => '3',
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame(0, $this->jobsRepository->getAllJobs()->count('*'));
        $this->assertSame(0, $this->scheduleRepository->getTable()->count('*'));
    }
}
