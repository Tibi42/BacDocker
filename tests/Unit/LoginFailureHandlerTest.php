<?php

namespace App\Tests\Unit;

use App\Security\LoginFailureHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginFailureHandlerTest extends TestCase
{
    public function testRedirectsToHomeWithLoginOpenAndStoresError(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_home', ['open' => 'login'])
            ->willReturn('/?open=login');

        $handler = new LoginFailureHandler($urlGenerator);

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $exception = new AuthenticationException('bad credentials');

        $response = $handler->onAuthenticationFailure($request, $exception);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/?open=login', $response->getTargetUrl());
        $this->assertSame($exception, $session->get('_security.last_error'));
    }
}
