<?php

namespace Crm\ScenariosModule\Tests;

use Crm\ScenariosModule\Scenarios\HasPaymentCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HasPaymentCriteriaTest extends TestCase
{
    #[DataProvider('hasPaymentDataProvider')]
    public function testHasPaymentWithoutPayment(array $parameters, bool $selectedValue, bool $expectedResult): void
    {
        $hasPaymentCriteria = new HasPaymentCriteria();
        $result = $hasPaymentCriteria->evaluate((object)$parameters, [
            HasPaymentCriteria::KEY => (object)['selection' => $selectedValue],
        ]);

        $this->assertEquals($expectedResult, $result);
    }

    public static function hasPaymentDataProvider(): array
    {
        return [
            [['payment_id' => null], true, false],
            [['payment_id' => 1], true, true],
            [['payment_id' => 1], false, false],
            [['payment_id' => null], false, true],
            [[], false, true],
        ];
    }
}
