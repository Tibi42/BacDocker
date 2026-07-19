<?php

namespace App\EventSubscriber;

use App\Twig\CspExtension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Génère un nonce CSP par requête et pose les en-têtes de sécurité HTTP.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->attributes->has(CspExtension::REQUEST_ATTRIBUTE)) {
            $request->attributes->set(CspExtension::REQUEST_ATTRIBUTE, base64_encode(random_bytes(16)));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $nonce = (string) $event->getRequest()->attributes->get(CspExtension::REQUEST_ATTRIBUTE, '');

        $headers->set('X-Content-Type-Options', 'nosniff');
        // Scripts : nonce (plus de unsafe-inline). Styles : unsafe-inline encore requis (Quill).
        $scriptSrc = $nonce !== ''
            ? "script-src 'self' 'nonce-{$nonce}' data:"
            : "script-src 'self' data:";

        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; {$scriptSrc}; style-src 'self' 'unsafe-inline' data:; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.helloasso.com"
        );
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($this->environment === 'prod') {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $csp = (string) $headers->get('Content-Security-Policy');
            $headers->set('Content-Security-Policy', $csp . '; upgrade-insecure-requests');
        }
    }
}
