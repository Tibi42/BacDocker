<?php

namespace App\Tests\Unit;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSubscribesToKernelResponse(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame('onKernelResponse', $events[KernelEvents::RESPONSE]);
    }

    public function testSetsSecurityHeadersOnMainRequest(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');
        $response = new Response();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function testSetsHstsInProd(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $response = new Response();
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        $this->assertSame('max-age=31536000; includeSubDomains', $response->headers->get('Strict-Transport-Security'));
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
