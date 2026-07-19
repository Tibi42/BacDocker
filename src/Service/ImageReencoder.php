<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ré-encode les images uploadées pour supprimer tout contenu polyglotte / embarqué.
 * Peut aussi redimensionner (ex. jaquettes de ludothèque) pour limiter le poids.
 */
final class ImageReencoder
{
    private const ABSOLUTE_MAX_WIDTH = 4096;
    private const ABSOLUTE_MAX_HEIGHT = 4096;

    /**
     * @param int|null $maxWidth  Largeur max de sortie (null = pas de resize)
     * @param int|null $maxHeight Hauteur max de sortie (null = pas de resize)
     * @param int      $jpegQuality Qualité JPEG / WebP (1–100)
     *
     * @return string|null Nom de fichier final (avec extension) ou null en cas d'échec
     */
    public function reencode(
        UploadedFile $file,
        string $directory,
        string $basename,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        int $jpegQuality = 85,
    ): ?string {
        $pathname = $file->getPathname();
        $mime = (string) $file->getMimeType();

        $dimensions = @getimagesize($pathname);
        if (
            $dimensions === false
            || $dimensions[0] > self::ABSOLUTE_MAX_WIDTH
            || $dimensions[1] > self::ABSOLUTE_MAX_HEIGHT
        ) {
            return null;
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($pathname),
            'image/png' => @imagecreatefrompng($pathname),
            'image/webp' => @imagecreatefromwebp($pathname),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $image = $this->resizeIfNeeded($image, $srcW, $srcH, $maxWidth, $maxHeight);

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = $basename . '.' . $extension;
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $saved = match ($extension) {
            'jpg' => imagejpeg($image, $target, $jpegQuality),
            'png' => imagepng($image, $target, 6),
            'webp' => imagewebp($image, $target, $jpegQuality),
            default => false,
        };

        imagedestroy($image);

        return $saved ? $filename : null;
    }

    /**
     * @param \GdImage $image
     *
     * @return \GdImage
     */
    private function resizeIfNeeded(\GdImage $image, int $srcW, int $srcH, ?int $maxWidth, ?int $maxHeight): \GdImage
    {
        if ($maxWidth === null && $maxHeight === null) {
            return $image;
        }

        $limitW = $maxWidth ?? $srcW;
        $limitH = $maxHeight ?? $srcH;

        if ($srcW <= $limitW && $srcH <= $limitH) {
            return $image;
        }

        $ratio = min($limitW / $srcW, $limitH / $srcH);
        $dstW = max(1, (int) round($srcW * $ratio));
        $dstH = max(1, (int) round($srcH * $ratio));

        $resized = imagecreatetruecolor($dstW, $dstH);
        if ($resized === false) {
            return $image;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $dstW, $dstH, $transparent);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($image);

        return $resized;
    }
}
