<?php

namespace App\Tests\Unit;

use App\EventSubscriber\NotFoundExceptionSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class NotFoundExceptionSubscriberTest extends TestCase
{
    public function testSubscribesToKernelException(): void
    {
        $events = NotFoundExceptionSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        $this->assertSame(['onKernelException', -100], $events[KernelEvents::EXCEPTION]);
    }

    public function testRendersCustom404ForHtmlRequests(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Twig/Exception/error404.html.twig', $this->callback(function (array $ctx): bool {
                return ($ctx['status_code'] ?? null) === 404;
            }))
            ->willReturn('<html>404</html>');

        $subscriber = new NotFoundExceptionSubscriber($twig);
        $request = Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('Not found')
        );

        $subscriber->onKernelException($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('<html>404</html>', $response->getContent());
        $this->assertTrue($event->isPropagationStopped());
    }

    public function testSkipsNon404Exceptions(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $subscriber = new NotFoundExceptionSubscriber($twig);
        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('/'),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('boom')
        );

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testSkipsJsonAccept(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $subscriber = new NotFoundExceptionSubscriber($twig);
        $request = Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $request->setRequestFormat('json');

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException()
        );

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testLogsWhenTwigFails(): void
    {
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willThrowException(new \RuntimeException('twig down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('404'),
                $this->arrayHasKey('message')
            );

        $subscriber = new NotFoundExceptionSubscriber($twig, $logger);
        $request = Request::create('/missing', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'text/html',
        ]);

        $event = new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException()
        );

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }
}
