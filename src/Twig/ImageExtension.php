<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtres d'images pour privilégier WebP quand un équivalent existe.
 */
final class ImageExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('prefer_webp', $this->preferWebp(...)),
            new TwigFilter('responsive_small', $this->responsiveSmall(...)),
        ];
    }

    /**
     * Remplace .jpg/.jpeg/.png par .webp uniquement si le fichier WebP existe
     * réellement sous public/images/{$subdir}/. Sinon conserve l'original
     * (cas des uploads admin ré-encodés en JPEG/PNG sans variante WebP).
     */
    public function preferWebp(?string $filename, string $subdir = 'articles'): string
    {
        if ($filename === null || $filename === '') {
            return '';
        }

        if (preg_match('/\.(jpe?g|png)$/i', $filename) !== 1) {
            return $filename;
        }

        $webp = (string) preg_replace('/\.(jpe?g|png)$/i', '.webp', $filename);
        $path = $this->projectDir . '/public/images/' . $subdir . '/' . $webp;

        return is_file($path) ? $webp : $filename;
    }

    /**
     * Retourne le nom de la variante "-480" du fichier si elle existe réellement
     * sur le disque (images/{$subdir}/), sinon null. Évite de générer un srcset
     * pointant vers une ressource manquante pour des images uploadées via l'admin
     * qui n'ont pas de variante réduite pré-générée.
     */
    public function responsiveSmall(?string $filename, string $subdir): ?string
    {
        if ($filename === null || $filename === '' || !str_ends_with(strtolower($filename), '.webp')) {
            return null;
        }

        $small = substr($filename, 0, -\strlen('.webp')) . '-480.webp';
        $path = $this->projectDir . '/public/images/' . $subdir . '/' . $small;

        return is_file($path) ? $small : null;
    }
}
