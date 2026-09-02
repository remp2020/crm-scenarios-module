<?php

declare(strict_types=1);

namespace Crm\ScenariosModule\Tests\Scenarios;

use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\ScenariosModule\Scenarios\TimeframeScenarioTrait;
use Nette\Utils\DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeframeScenarioTraitTest extends TestCase
{
    use TimeframeScenarioTrait;

    private const string TIMEFRAME_KEY = 'timeframe';
    private const array UNITS = ['days', 'weeks'];
    private const array OPERATORS = ['in the last', 'before'];

    public static function emptyTimeframeDataProvider(): array
    {
        return [
            'NoValues_ReturnsNull' => [
                'values' => [],
            ],
            'ClearedAmount_ReturnsNull' => [
                'values' => ['selection' => ''],
            ],
            'OnlyPreselectedSingleOperator_ReturnsNull' => [
                'values' => ['operator' => 'in the last'],
                'operators' => ['in the last'],
            ],
        ];
    }

    #[DataProvider('emptyTimeframeDataProvider')]
    public function testEmptyTimeframeReturnsNull(array $values, array $operators = self::OPERATORS): void
    {
        $timeframe = $this->getTimeframe([self::TIMEFRAME_KEY => (object) $values], self::UNITS, $operators, self::TIMEFRAME_KEY);

        $this->assertNull($timeframe);
    }

    public static function incompleteTimeframeDataProvider(): array
    {
        return [
            'OnlyOperator_Throws' => [
                'values' => ['operator' => 'before'],
            ],
            'OnlyAmount_Throws' => [
                'values' => ['selection' => 3],
            ],
            'MissingAmount_Throws' => [
                'values' => ['operator' => 'before', 'unit' => 'days'],
            ],
            'MissingUnit_Throws' => [
                'values' => ['operator' => 'before', 'selection' => 3],
            ],
            'MissingOperator_Throws' => [
                'values' => ['unit' => 'days', 'selection' => 3],
            ],
            'PreselectedSingleOperatorWithUnitOnly_Throws' => [
                'values' => ['operator' => 'in the last', 'unit' => 'days'],
                'operators' => ['in the last'],
            ],
        ];
    }

    #[DataProvider('incompleteTimeframeDataProvider')]
    public function testIncompleteTimeframeThrows(array $values, array $operators = self::OPERATORS): void
    {
        $this->expectException(ConditionCheckException::class);
        $this->expectExceptionMessage('Timeframe [' . self::TIMEFRAME_KEY . '] is incomplete');

        $this->getTimeframe([self::TIMEFRAME_KEY => (object) $values], self::UNITS, $operators, self::TIMEFRAME_KEY);
    }

    public function testCompleteTimeframeReturnsLimitAndOperator(): void
    {
        $this->setNow(new DateTime('2026-09-01 12:00:00'));

        $timeframe = $this->getTimeframe([self::TIMEFRAME_KEY => (object) [
            'operator' => 'before',
            'unit' => 'days',
            'selection' => '3',
        ]], self::UNITS, self::OPERATORS, self::TIMEFRAME_KEY);

        $this->assertSame('before', $timeframe['operator']);
        $this->assertEquals(new DateTime('2026-08-29 12:00:00'), $timeframe['limit']);
    }
}
