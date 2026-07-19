<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le nonce CSP de la requête courante aux templates.
 */
final class CspExtension extends AbstractExtension
{
    public const REQUEST_ATTRIBUTE = '_csp_nonce';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->getNonce(...)),
        ];
    }

    public function getNonce(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $nonce = $request->attributes->get(self::REQUEST_ATTRIBUTE);
        if (!\is_string($nonce) || $nonce === '') {
            $nonce = base64_encode(random_bytes(16));
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $nonce);
        }

        return $nonce;
    }
}
