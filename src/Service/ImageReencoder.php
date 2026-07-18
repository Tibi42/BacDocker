<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Ré-encode les images uploadées pour supprimer tout contenu polyglotte / embarqué.
 */
final class ImageReencoder
{
    private const MAX_WIDTH = 4096;
    private const MAX_HEIGHT = 4096;

    /**
     * @return string|null Nom de fichier final (avec extension) ou null en cas d'échec
     */
    public function reencode(UploadedFile $file, string $directory, string $basename): ?string
    {
        $pathname = $file->getPathname();
        $mime = (string) $file->getMimeType();

        $dimensions = @getimagesize($pathname);
        if (
            $dimensions === false
            || $dimensions[0] > self::MAX_WIDTH
            || $dimensions[1] > self::MAX_HEIGHT
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

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = $basename . '.' . $extension;
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        $saved = match ($extension) {
            'jpg' => imagejpeg($image, $target, 85),
            'png' => imagepng($image, $target, 6),
            'webp' => imagewebp($image, $target, 85),
            default => false,
        };

        imagedestroy($image);

        return $saved ? $filename : null;
    }
}
