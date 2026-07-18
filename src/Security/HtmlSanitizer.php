<?php

namespace App\Security;

/**
 * Sanitizer HTML allowlist pour le contenu WYSIWYG des articles.
 * Supprime scripts, handlers d'événements et URL dangereuses (javascript:, data:, etc.).
 */
final class HtmlSanitizer
{
    private const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'link', 'meta',
        'form', 'input', 'button', 'textarea', 'select', 'option',
    ];

    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'img',
        'span', 'div', 'figure', 'figcaption', 'hr', 'table', 'thead',
        'tbody', 'tr', 'th', 'td', 'pre', 'code',
    ];

    private const ALLOWED_ATTRS = [
        'href', 'src', 'alt', 'title', 'class', 'width', 'height',
        'colspan', 'rowspan', 'target', 'rel',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="__sanitize_root">' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__sanitize_root');
        if (!$root) {
            return strip_tags($html);
        }

        $this->cleanNode($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    private function cleanNode(\DOMNode $node): void
    {
        // 1. Recurse into children first (bottom-up) so they are cleaned before we modify the parent
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $this->cleanNode($child);
        }

        // 2. Clean the node itself
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);

            if (\in_array($tag, self::DANGEROUS_TAGS, true)) {
                $node->parentNode?->removeChild($node);
                return;
            }

            if (!\in_array($tag, self::ALLOWED_TAGS, true)) {
                // Remplacer le nœud interdit par ses enfants (déjà nettoyés)
                while ($node->firstChild) {
                    $node->parentNode?->insertBefore($node->firstChild, $node);
                }
                $node->parentNode?->removeChild($node);
                return;
            }

            $attrsToRemove = [];
            foreach ($node->attributes ?? [] as $attr) {
                $name = strtolower($attr->name);
                if (!\in_array($name, self::ALLOWED_ATTRS, true) || str_starts_with($name, 'on')) {
                    $attrsToRemove[] = $attr->name;
                    continue;
                }

                if (\in_array($name, ['href', 'src'], true) && !$this->isSafeUrl($attr->value)) {
                    $attrsToRemove[] = $attr->name;
                }
            }

            foreach ($attrsToRemove as $attrName) {
                $node->removeAttribute($attrName);
            }

            if ($tag === 'a') {
                $node->setAttribute('rel', 'noopener noreferrer');
                if ($node->hasAttribute('target') && $node->getAttribute('target') !== '_blank') {
                    $node->removeAttribute('target');
                }
            }
        }
    }

    public function isSafeUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $url = trim($url);

        // Chemins relatifs internes
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }

        // Ancres
        if (str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https', 'mailto'], true);
    }
}
