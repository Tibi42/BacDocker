<?php

namespace App\Tests\Unit;

use App\Security\HtmlSanitizer;
use App\Twig\SanitizeExtension;
use PHPUnit\Framework\TestCase;

class SanitizeExtensionTest extends TestCase
{
    public function testRegistersFilters(): void
    {
        $extension = new SanitizeExtension(new HtmlSanitizer());
        $names = array_map(static fn ($f) => $f->getName(), $extension->getFilters());

        $this->assertContains('sanitize_html', $names);
        $this->assertContains('safe_url', $names);
    }

    public function testSanitizeRemovesDangerousHtml(): void
    {
        $extension = new SanitizeExtension(new HtmlSanitizer());

        $result = $extension->sanitize('<p>ok</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>ok</p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSanitizeNullReturnsEmptyString(): void
    {
        $extension = new SanitizeExtension(new HtmlSanitizer());

        $this->assertSame('', $extension->sanitize(null));
    }

    public function testSafeUrlKeepsSafeAndRejectsDangerous(): void
    {
        $extension = new SanitizeExtension(new HtmlSanitizer());

        $this->assertSame('/nouvelles', $extension->safeUrl('/nouvelles'));
        $this->assertSame('https://example.com', $extension->safeUrl('https://example.com'));
        $this->assertNull($extension->safeUrl('javascript:alert(1)'));
        $this->assertNull($extension->safeUrl(null));
        $this->assertNull($extension->safeUrl(''));
    }
}
