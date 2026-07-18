<?php

namespace App\Twig;

use App\Repository\CarouselSlideRepository;
use App\Security\HtmlSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig fournissant la fonction carousel_slides().
 *
 * Retourne la liste des slides du carrousel depuis la base de données.
 * Si la table est vide, des slides de démonstration codées en dur sont
 * utilisées en fallback pour éviter un carrousel vide lors du premier
 * déploiement ou en développement.
 */
class CarouselExtension extends AbstractExtension
{
    public function __construct(
        private readonly CarouselSlideRepository $carouselSlideRepository,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('carousel_slides', $this->getCarouselSlides(...)),
        ];
    }

    /**
     * @return array<int, array{tag: string, tag_color: string, title: string, date: string, btn_text: string, btn_class: string, btn_url: string|null}>
     */
    public function getCarouselSlides(): array
    {
        $entities = $this->carouselSlideRepository->findActiveOrderByPosition();
        if (\count($entities) > 0) {
            $slides = [];
            foreach ($entities as $s) {
                $slides[] = [
                    'tag' => $s->getTag() ?? '',
                    'tag_color' => $s->getTagColor() ?? 'text-custom-orange',
                    'title' => $s->getTitle() ?? '',
                    'date' => $s->getDate() ?? '',
                    'btn_text' => $s->getBtnText() ?? '',
                    'btn_class' => $s->getBtnClass() ?? 'bg-custom-orange group-hover:bg-orange-600 shadow-custom-orange/20',
                    'btn_url' => $this->sanitizeBtnUrl($s->getBtnUrl()),
                ];
            }
            return $slides;
        }

        return [
            [
                'tag' => 'Prochain événement',
                'tag_color' => 'text-custom-orange',
                'title' => 'Senses Etch - Champs de Valoris',
                'date' => '25 JAN',
                'btn_text' => "S'INSCRIRE",
                'btn_class' => 'bg-custom-orange group-hover:bg-orange-600 shadow-custom-orange/20',
                'btn_url' => null,
            ],
            [
                'tag' => 'Nouvel article',
                'tag_color' => 'text-cyan-400',
                'title' => 'Guide : Peindre ses figurines',
                'date' => 'PUBLIÉ LUNDI',
                'btn_text' => 'LIRE LA SUITE',
                'btn_class' => 'bg-cyan-600 group-hover:bg-cyan-700 shadow-cyan-500/20',
                'btn_url' => null,
            ],
            [
                'tag' => 'Vie du forum',
                'tag_color' => 'text-purple-400',
                'title' => 'Organisation GN été 2026',
                'date' => 'EN DISCUSSION',
                'btn_text' => 'PARTICIPER',
                'btn_class' => 'bg-purple-600 group-hover:bg-purple-700 shadow-purple-500/20',
                'btn_url' => null,
            ],
            [
                'tag' => 'Soirée spéciale',
                'tag_color' => 'text-rose-400',
                'title' => 'Soirée Jeux de société Expert',
                'date' => '12 FÉVRIER',
                'btn_text' => 'RÉSERVER',
                'btn_class' => 'bg-rose-600 group-hover:bg-rose-700 shadow-rose-500/20',
                'btn_url' => null,
            ],
            [
                'tag' => 'Association',
                'tag_color' => 'text-emerald-400',
                'title' => 'Nouvel arrivage : 15 nouveaux jeux',
                'date' => 'DISPONIBLES AU LOCAL',
                'btn_text' => 'VOIR LA LISTE',
                'btn_class' => 'bg-emerald-600 group-hover:bg-emerald-700 shadow-emerald-500/20',
                'btn_url' => null,
            ],
        ];
    }

    private function sanitizeBtnUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return $this->htmlSanitizer->isSafeUrl($url) ? $url : null;
    }
}
