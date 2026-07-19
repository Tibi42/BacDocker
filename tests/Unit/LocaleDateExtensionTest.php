<?php

namespace App\Tests\Unit;

use App\Twig\LocaleDateExtension;
use PHPUnit\Framework\TestCase;

class LocaleDateExtensionTest extends TestCase
{
    public function testRegistersDateFrFilter(): void
    {
        $extension = new LocaleDateExtension();
        $filters = $extension->getFilters();

        $this->assertCount(1, $filters);
        $this->assertSame('date_fr', $filters[0]->getName());
    }

    public function testFormatDateFrReturnsFrenchDate(): void
    {
        $extension = new LocaleDateExtension();
        $date = new \DateTimeImmutable('2025-04-12');

        $result = $extension->formatDateFr($date);

        $this->assertStringContainsString('2025', $result);
        $this->assertMatchesRegularExpression('/avril|April|12/i', $result);
        // fr_FR : le jour/mois en lettres
        $this->assertStringContainsString('12', $result);
    }

    public function testFormatDateFrAcceptsCustomPattern(): void
    {
        $extension = new LocaleDateExtension();
        $date = new \DateTimeImmutable('2025-04-12');

        $result = $extension->formatDateFr($date, 'dd/MM/yyyy');

        $this->assertSame('12/04/2025', $result);
    }
}
