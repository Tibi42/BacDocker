<?php

namespace App\Tests\Unit;

use App\Util\CsvCellSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellSanitizerTest extends TestCase
{
    #[DataProvider('dangerousValuesProvider')]
    public function testPrefixesDangerousFormulas(string $value): void
    {
        $result = CsvCellSanitizer::sanitize($value);

        $this->assertSame("\t" . $value, $result);
    }

    public static function dangerousValuesProvider(): array
    {
        return [
            'equals' => ['=1+1'],
            'plus' => ['+cmd'],
            'minus' => ['-2+2'],
            'at' => ['@SUM(A1)'],
            'tab' => ["\tformula"],
            'cr' => ["\rformula"],
        ];
    }

    public function testLeavesSafeValuesUnchanged(): void
    {
        $this->assertSame('Alice', CsvCellSanitizer::sanitize('Alice'));
        $this->assertSame('hello@example.com', CsvCellSanitizer::sanitize('hello@example.com'));
        $this->assertSame('', CsvCellSanitizer::sanitize(''));
        $this->assertSame('42', CsvCellSanitizer::sanitize('42'));
    }
}
