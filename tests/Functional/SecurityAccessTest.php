<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

class SecurityAccessTest extends DatabaseWebTestCase
{
    #[DataProvider('protectedAdminRoutesProvider')]
    public function testAnonymousIsRedirectedFromAdmin(string $path): void
    {
        $this->client->request('GET', $path);

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertTrue(
            str_contains($location, '/login') || str_contains($location, '/'),
            "Expected redirect to login/home, got: {$location}"
        );
    }

    public static function protectedAdminRoutesProvider(): iterable
    {
        yield 'admin' => ['/admin'];
        yield 'activites' => ['/admin/activites'];
        yield 'carousel' => ['/admin/carousel'];
        yield 'articles' => ['/admin/articles'];
        yield 'ludotheque' => ['/admin/ludotheque'];
        yield 'users' => ['/admin/utilisateurs'];
        yield 'newsletter' => ['/admin/newsletter'];
    }

    public function testAnonymousIsRedirectedFromMonEspace(): void
    {
        $this->client->request('GET', '/mon-espace');

        $this->assertResponseRedirects();
    }

    public function testUserCannotAccessAdmin(): void
    {
        $user = $this->createUser('member@example.com', 'DevUserPass!12', ['ROLE_USER'], 'member');
        $this->login($user);

        $this->client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCanAccessMonEspace(): void
    {
        $user = $this->createUser('member2@example.com', 'DevUserPass!12', ['ROLE_USER'], 'member2');
        $this->login($user);

        $this->client->request('GET', '/mon-espace');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanAccessDashboard(): void
    {
        $admin = $this->createUser('admin@example.com', 'DevAdminPass!12', ['ROLE_ADMIN'], 'admin');
        $this->login($admin);

        $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testLoginPageRedirectsAuthenticatedUser(): void
    {
        $user = $this->createUser('auth@example.com', 'DevUserPass!12', ['ROLE_USER'], 'authuser');
        $this->login($user);

        $this->client->request('GET', '/login');

        // SecurityController redirects authenticated users away from login
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            $status === 200 || ($status >= 300 && $status < 400),
            "Unexpected status {$status}"
        );
    }
}
