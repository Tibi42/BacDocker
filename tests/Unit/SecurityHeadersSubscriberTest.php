<?php

namespace App\Tests\Unit;

use App\EventSubscriber\SecurityHeadersSubscriber;
use App\Twig\CspExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSubscribesToKernelRequestAndResponse(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame('onKernelResponse', $events[KernelEvents::RESPONSE]);
    }

    public function testGeneratesNonceOnMainRequest(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');
        $request = new Request();
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber->onKernelRequest($event);

        $nonce = $request->attributes->get(CspExtension::REQUEST_ATTRIBUTE);
        $this->assertIsString($nonce);
        $this->assertNotSame('', $nonce);
    }

    public function testSetsSecurityHeadersOnMainRequest(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');
        $request = new Request();
        $request->attributes->set(CspExtension::REQUEST_ATTRIBUTE, 'test-nonce-value');
        $response = new Response();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('X-XSS-Protection'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'self' 'nonce-test-nonce-value'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $csp);
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function testSetsHstsInProd(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $request = new Request();
        $request->attributes->set(CspExtension::REQUEST_ATTRIBUTE, 'prod-nonce');
        $response = new Response();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        $this->assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
        $this->assertStringContainsString('upgrade-insecure-requests', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');
        $response = new Response();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        $this->assertNull($response->headers->get('X-Frame-Options'));
    }
}
