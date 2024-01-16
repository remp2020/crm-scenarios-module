<?php

namespace Crm\ScenariosModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Seeders\SegmentsTrait;
use Nette\Database\Table\ActiveRow;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentGroupsSeeder implements ISeeder
{
    use SegmentsTrait;

    public const AB_TEST_SEGMENT_GROUP_CODE = 'scenarios-ab-test';

    public function __construct(
        private SegmentGroupsRepository $segmentGroupsRepository,
        private SegmentsRepository $segmentsRepository,
    ) {
        $this->segmentGroupsRepository = $segmentGroupsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->seedSegmentGroup(
            $output,
            'Scenarios - AB Test',
            self::AB_TEST_SEGMENT_GROUP_CODE,
            1800
        );
    }

    public static function getSegmentCode(ActiveRow $elementRow, string $code): string
    {
        return "scenarios_ab_test_element_{$elementRow->id}_variant_{$code}";
    }
}
