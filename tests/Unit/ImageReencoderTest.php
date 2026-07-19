<?php

namespace App\Tests\Unit;

use App\Service\ImageReencoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageReencoderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bac_img_' . uniqid('', true);
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    public function testReencodeJpegReturnsFilename(): void
    {
        $source = $this->createTempJpeg(40, 30);
        $file = new UploadedFile($source, 'photo.jpg', 'image/jpeg', null, true);

        $reencoder = new ImageReencoder();
        $filename = $reencoder->reencode($file, $this->tmpDir, 'game_cover');

        $this->assertSame('game_cover.jpg', $filename);
        $this->assertFileExists($this->tmpDir . DIRECTORY_SEPARATOR . $filename);
    }

    public function testReencodeResizesWhenLargerThanMax(): void
    {
        $source = $this->createTempJpeg(200, 100);
        $file = new UploadedFile($source, 'big.jpg', 'image/jpeg', null, true);

        $reencoder = new ImageReencoder();
        $filename = $reencoder->reencode($file, $this->tmpDir, 'resized', 50, 50);

        $this->assertSame('resized.jpg', $filename);
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $filename;
        $size = getimagesize($path);
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(50, $size[0]);
        $this->assertLessThanOrEqual(50, $size[1]);
    }

    public function testReencodeRejectsUnsupportedMime(): void
    {
        $source = $this->tmpDir . DIRECTORY_SEPARATOR . 'plain.txt';
        file_put_contents($source, 'not an image');
        // getimagesize will fail → null
        $file = new UploadedFile($source, 'plain.txt', 'text/plain', null, true);

        $reencoder = new ImageReencoder();
        $filename = $reencoder->reencode($file, $this->tmpDir, 'fail');

        $this->assertNull($filename);
    }

    private function createTempJpeg(int $width, int $height): string
    {
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . 'src_' . uniqid('', true) . '.jpg';
        $img = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($img, 200, 50, 50);
        imagefilledrectangle($img, 0, 0, $width, $height, $color);
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }
}
