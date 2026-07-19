<?php

namespace App\Tests\Unit;

use App\Twig\ImageExtension;
use PHPUnit\Framework\TestCase;

class ImageExtensionTest extends TestCase
{
    public function testPreferWebpReplacesCommonExtensions(): void
    {
        $ext = new ImageExtension(sys_get_temp_dir());

        $this->assertSame('article-1.webp', $ext->preferWebp('article-1.jpg'));
        $this->assertSame('article-3.webp', $ext->preferWebp('article-3.png'));
        $this->assertSame('photo.webp', $ext->preferWebp('photo.jpeg'));
        $this->assertSame('already.webp', $ext->preferWebp('already.webp'));
        $this->assertSame('', $ext->preferWebp(null));
    }

    public function testResponsiveSmallReturnsNullWhenVariantMissing(): void
    {
        $ext = new ImageExtension(sys_get_temp_dir());

        $this->assertNull($ext->responsiveSmall('missing.webp', 'board-games'));
        $this->assertNull($ext->responsiveSmall('photo.jpg', 'board-games'));
        $this->assertNull($ext->responsiveSmall(null, 'board-games'));
    }
}
