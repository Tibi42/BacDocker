<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

class PublicPagesTest extends DatabaseWebTestCase
{
    #[DataProvider('publicRoutesProvider')]
    public function testPublicPageReturnsSuccessfulResponse(string $path): void
    {
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    public static function publicRoutesProvider(): iterable
    {
        yield 'home' => ['/'];
        yield 'jds' => ['/jds'];
        yield 'jdr' => ['/jdr'];
        yield 'gn' => ['/gn'];
        yield 'association' => ['/association'];
        yield 'nouvelles' => ['/nouvelles'];
        yield 'assaut_dragons' => ['/nouvelles/compte-rendu-assaut-des-dragons'];
        yield 'qui_sommes_nous' => ['/qui-sommes-nous'];
        yield 'societes' => ['/societes'];
        yield 'evenements' => ['/evenements'];
        yield 'mentions_legales' => ['/mentions-legales'];
        yield 'contact' => ['/contact'];
        yield 'soiree_heb' => ['/nos/soiree/heb'];
        yield 'soiree_biheb' => ['/nos/soiree/biheb'];
        yield 'soiree_mensuelle' => ['/nos/soiree/mensuelle'];
        yield 'ludotheque' => ['/ludotheque'];
        yield 'reset_password' => ['/reset-password'];
        yield 'reset_check_email' => ['/reset-password/check-email'];
    }

    public function testLoginRedirectsToHomeWithLoginOpen(): void
    {
        $this->client->request('GET', '/login');

        $this->assertResponseRedirects('/?open=login');
    }

    public function testCalendarTurboFrameReturnsLightweightResponse(): void
    {
        $this->client->request('GET', '/?month=7&year=2026', server: [
            'HTTP_TURBO_FRAME' => 'calendar-desktop-frame',
        ]);

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('id="calendar-desktop-frame"', $content);
        $this->assertStringContainsString('id="calendar-frame"', $content);
        $this->assertStringNotContainsString('hero-brand', $content);
        $this->assertStringNotContainsString('DERNIÈRES CHIMÈRES', $content);
    }

    public function testSitemapReturnsXml(): void
    {
        $this->client->request('GET', '/sitemap.xml');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/xml');
        $this->assertStringContainsString('<urlset', $this->client->getResponse()->getContent());
    }

    public function testLudothequeSuggestReturnsJson(): void
    {
        $this->client->request('GET', '/ludotheque/suggest?q=cat');

        $this->assertResponseIsSuccessful();
        $this->assertJson($this->client->getResponse()->getContent());
    }

    public function testUnknownRouteRendersCustom404(): void
    {
        $this->client->request('GET', '/cette-page-n-existe-pas-xyz');

        $this->assertResponseStatusCodeSame(404);
    }
}
