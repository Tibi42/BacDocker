<?php

namespace App\Tests\Unit;

use App\Twig\ImageExtension;
use PHPUnit\Framework\TestCase;

class ImageExtensionTest extends TestCase
{
    private string $projectDir;
    private string $articlesDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/bac_image_ext_' . uniqid();
        $this->articlesDir = $this->projectDir . '/public/images/articles';
        mkdir($this->articlesDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->articlesDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->articlesDir);
        @rmdir($this->projectDir . '/public/images');
        @rmdir($this->projectDir . '/public');
        @rmdir($this->projectDir);
    }

    public function testPreferWebpUsesWebpWhenFileExists(): void
    {
        file_put_contents($this->articlesDir . '/article-1.webp', 'webp');
        $ext = new ImageExtension($this->projectDir);

        $this->assertSame('article-1.webp', $ext->preferWebp('article-1.jpg'));
        $this->assertSame('article-1.webp', $ext->preferWebp('article-1.png'));
    }

    public function testPreferWebpKeepsOriginalWhenWebpMissing(): void
    {
        file_put_contents($this->articlesDir . '/upload.jpg', 'jpg');
        $ext = new ImageExtension($this->projectDir);

        $this->assertSame('upload.jpg', $ext->preferWebp('upload.jpg'));
        $this->assertSame('upload.png', $ext->preferWebp('upload.png'));
    }

    public function testPreferWebpPassthrough(): void
    {
        $ext = new ImageExtension($this->projectDir);

        $this->assertSame('already.webp', $ext->preferWebp('already.webp'));
        $this->assertSame('', $ext->preferWebp(null));
        $this->assertSame('', $ext->preferWebp(''));
    }

    public function testResponsiveSmallReturnsNullWhenVariantMissing(): void
    {
        $ext = new ImageExtension($this->projectDir);

        $this->assertNull($ext->responsiveSmall('missing.webp', 'articles'));
        $this->assertNull($ext->responsiveSmall('photo.jpg', 'articles'));
        $this->assertNull($ext->responsiveSmall(null, 'articles'));
    }

    public function testResponsiveSmallReturnsVariantWhenPresent(): void
    {
        file_put_contents($this->articlesDir . '/article-1-480.webp', 'small');
        $ext = new ImageExtension($this->projectDir);

        $this->assertSame('article-1-480.webp', $ext->responsiveSmall('article-1.webp', 'articles'));
    }
}
