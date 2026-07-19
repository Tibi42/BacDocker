<?php

namespace App\Tests\Unit;

use App\Enum\ActivityKind;
use PHPUnit\Framework\TestCase;

class ActivityKindTest extends TestCase
{
    public function testValuesContainsAllCases(): void
    {
        $values = ActivityKind::values();

        $this->assertContains('JDS', $values);
        $this->assertContains('JDR', $values);
        $this->assertContains('GN', $values);
        $this->assertContains('JDF', $values);
        $this->assertContains('AG', $values);
        $this->assertContains('Play Test', $values);
        $this->assertCount(count(ActivityKind::cases()), $values);
    }

    public function testLabelsAreReadable(): void
    {
        $this->assertSame('JDS (Jeux de Société)', ActivityKind::JDS->label());
        $this->assertSame('JDR (Jeux de Rôle)', ActivityKind::JDR->label());
        $this->assertSame('GN (Grandeur Nature)', ActivityKind::GN->label());
        $this->assertSame('JDF (Jeux de Figurines)', ActivityKind::JDF->label());
        $this->assertSame('AG (Assemblée Générale)', ActivityKind::AG->label());
        $this->assertSame('Play Test', ActivityKind::PlayTest->label());
    }

    public function testFromString(): void
    {
        $this->assertSame(ActivityKind::JDS, ActivityKind::from('JDS'));
        $this->assertSame(ActivityKind::PlayTest, ActivityKind::from('Play Test'));
    }
}
