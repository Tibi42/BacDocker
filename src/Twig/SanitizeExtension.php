<?php

namespace App\Twig;

use App\Security\HtmlSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SanitizeExtension extends AbstractExtension
{
    public function __construct(
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('sanitize_html', $this->sanitize(...), ['is_safe' => ['html']]),
            new TwigFilter('safe_url', $this->safeUrl(...)),
        ];
    }

    public function sanitize(?string $html): string
    {
        return $this->htmlSanitizer->sanitize($html) ?? '';
    }

    public function safeUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return $this->htmlSanitizer->isSafeUrl($url) ? $url : null;
    }
}
