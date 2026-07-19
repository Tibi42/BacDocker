<?php

namespace App\Tests\Unit;

use App\Security\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    public function testRemovesScriptTags(): void
    {
        $result = $this->sanitizer->sanitize('<p>Hello</p><script>alert(1)</script>');

        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testRemovesEventHandlers(): void
    {
        $result = $this->sanitizer->sanitize('<p onclick="alert(1)">Click</p>');

        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('Click', $result);
    }

    public function testRemovesJavascriptUrls(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">link</a>');

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testKeepsSafeLinks(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com">ok</a>');

        $this->assertStringContainsString('https://example.com', $result);
        $this->assertStringContainsString('noopener', $result);
    }

    public function testIsSafeUrl(): void
    {
        $this->assertTrue($this->sanitizer->isSafeUrl('/nouvelles'));
        $this->assertTrue($this->sanitizer->isSafeUrl('https://example.com'));
        $this->assertFalse($this->sanitizer->isSafeUrl('javascript:alert(1)'));
        $this->assertFalse($this->sanitizer->isSafeUrl('//evil.com'));
        $this->assertFalse($this->sanitizer->isSafeUrl('data:text/html,alert(1)'));
    }

    public function testRemovesSvgAndTemplateTags(): void
    {
        $result = $this->sanitizer->sanitize('<p>ok</p><svg onload="alert(1)"></svg><template><script>x</script></template>');

        $this->assertStringContainsString('<p>ok</p>', $result);
        $this->assertStringNotContainsString('<svg', $result);
        $this->assertStringNotContainsString('<template', $result);
    }
}
