<?php

namespace App\Tests\Unit;

use App\EventSubscriber\ProductionSecretSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ProductionSecretSubscriberTest extends TestCase
{
    public function testBlocksPlaceholderSecretInProd(): void
    {
        $subscriber = new ProductionSecretSubscriber('prod', 'change_me_in_env_local');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $this->expectException(ServiceUnavailableHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testAllowsStrongSecretInProd(): void
    {
        $subscriber = new ProductionSecretSubscriber('prod', bin2hex(random_bytes(32)));
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);
        $this->addToAssertionCount(1);
    }

    public function testSubscribesToKernelRequest(): void
    {
        $events = ProductionSecretSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }
}
