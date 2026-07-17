<?php

namespace App\Twig;

use App\Repository\ArticleRepository;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig fournissant la fonction featured_articles().
 *
 * Retourne la liste des articles de la section "Dernières Chimères" depuis la base de données.
 * Si la table est vide, des articles de démonstration codés en dur sont
 * utilisés en fallback pour éviter une section vide lors du premier
 * déploiement ou en développement.
 */
class ArticleExtension extends AbstractExtension
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly RouterInterface $router,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('featured_articles', $this->getFeaturedArticles(...)),
        ];
    }

    /**
     * @return array<int, array{id: int|null, title: string, image: string, tag: string, url: string|null, hasContent: bool}>
     */
    public function getFeaturedArticles(): array
    {
        $entities = $this->articleRepository->findActiveOrderByPosition();
        if (\count($entities) > 0) {
            $articles = [];
            foreach ($entities as $a) {
                $articles[] = [
                    'id' => $a->getId(),
                    'title' => $a->getTitle() ?? '',
                    'image' => $a->getImage() ?? '',
                    'tag' => $a->getTag() ?? '',
                    'url' => $this->sanitizeArticleUrl($a->getUrl()),
                    'hasContent' => $a->getContent() !== null && trim($a->getContent()) !== '',
                ];
            }
            return $articles;
        }

        // Fallback avec les articles de base
        return [
            [
                'id' => null,
                'title' => "COMPTE-RENDU : L'ASSAUT DES DRAGONS",
                'image' => 'article-1.webp',
                'tag' => '0/3',
                'url' => $this->router->generate('app_article_assaut_dragons'),
                'hasContent' => false,
            ],
            [
                'id' => null,
                'title' => 'REPORTAGE PHOTO : GN DU CRÉPUSCULE',
                'image' => 'article-2.webp',
                'tag' => '0/0',
                'url' => null,
                'hasContent' => false,
            ],
            [
                'id' => null,
                'title' => 'GUIDE : PEINDRE SES FIGURINES',
                'image' => 'article-3.webp',
                'tag' => '0/3',
                'url' => null,
                'hasContent' => false,
            ],
            [
                'id' => null,
                'title' => 'NOUVEAUTÉ : LES SORTIES DE FÉVRIER',
                'image' => 'article-4.webp',
                'tag' => 'NEW',
                'url' => null,
                'hasContent' => false,
            ],
        ];
    }

    private function sanitizeArticleUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $url = trim($url);
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
