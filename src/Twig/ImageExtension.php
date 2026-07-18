<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtres d'images pour privilégier WebP quand un équivalent existe.
 */
final class ImageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('prefer_webp', $this->preferWebp(...)),
        ];
    }

    /**
     * Remplace .jpg/.jpeg/.png par .webp pour servir la version optimisée.
     */
    public function preferWebp(?string $filename): string
    {
        if ($filename === null || $filename === '') {
            return '';
        }

        return (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $filename);
    }
}
