<?php

namespace App\Security;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizer HTML allowlist pour le contenu WYSIWYG des articles.
 *
 * Délègue au composant symfony/html-sanitizer (v8.0.14+) tout en exposant
 * sanitize() / isSafeUrl() pour le reste de l'application.
 */
final class HtmlSanitizer
{
    private readonly SymfonyHtmlSanitizer $sanitizer;

    public function __construct()
    {
        $attrs = ['class', 'title'];
        $linkAttrs = [...$attrs, 'href', 'target', 'rel'];
        $imgAttrs = [...$attrs, 'src', 'alt', 'width', 'height'];
        $cellAttrs = [...$attrs, 'colspan', 'rowspan'];

        $config = (new HtmlSanitizerConfig())
            ->allowElement('p', $attrs)
            ->allowElement('br')
            ->allowElement('strong', $attrs)
            ->allowElement('b', $attrs)
            ->allowElement('em', $attrs)
            ->allowElement('i', $attrs)
            ->allowElement('u', $attrs)
            ->allowElement('s', $attrs)
            ->allowElement('ul', $attrs)
            ->allowElement('ol', $attrs)
            ->allowElement('li', $attrs)
            ->allowElement('a', $linkAttrs)
            ->allowElement('h1', $attrs)
            ->allowElement('h2', $attrs)
            ->allowElement('h3', $attrs)
            ->allowElement('h4', $attrs)
            ->allowElement('h5', $attrs)
            ->allowElement('h6', $attrs)
            ->allowElement('blockquote', $attrs)
            ->allowElement('img', $imgAttrs)
            ->allowElement('span', $attrs)
            ->allowElement('div', $attrs)
            ->allowElement('figure', $attrs)
            ->allowElement('figcaption', $attrs)
            ->allowElement('hr')
            ->allowElement('table', $attrs)
            ->allowElement('thead', $attrs)
            ->allowElement('tbody', $attrs)
            ->allowElement('tr', $attrs)
            ->allowElement('th', $cellAttrs)
            ->allowElement('td', $cellAttrs)
            ->allowElement('pre', $attrs)
            ->allowElement('code', $attrs)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(200_000);

        $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        return $this->sanitizer->sanitize($html);
    }

    public function isSafeUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        if (str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
