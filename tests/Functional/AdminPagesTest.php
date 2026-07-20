<?php

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Entity\Article;
use App\Entity\BoardGame;
use App\Entity\CarouselSlide;
use App\Entity\NewsletterSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;

class AdminPagesTest extends DatabaseWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->createUser('admin@example.com', 'DevAdminPass!12', ['ROLE_ADMIN'], 'admin');
        $this->login($admin);
    }

    #[DataProvider('adminIndexRoutesProvider')]
    public function testAdminIndexPagesAreSuccessful(string $path): void
    {
        $this->client->request('GET', $path);

        $this->assertResponseIsSuccessful();
    }

    public static function adminIndexRoutesProvider(): iterable
    {
        yield 'dashboard' => ['/admin'];
        yield 'activites' => ['/admin/activites'];
        yield 'activites_new' => ['/admin/activites/nouvelle'];
        yield 'carousel' => ['/admin/carousel'];
        yield 'carousel_new' => ['/admin/carousel/nouveau'];
        yield 'articles' => ['/admin/articles'];
        yield 'articles_new' => ['/admin/articles/nouveau'];
        yield 'ludotheque' => ['/admin/ludotheque'];
        yield 'ludotheque_new' => ['/admin/ludotheque/nouveau'];
        yield 'users' => ['/admin/utilisateurs'];
        yield 'users_new' => ['/admin/utilisateurs/nouveau'];
        yield 'newsletter' => ['/admin/newsletter'];
    }

    public function testAdminCanOpenActivityEdit(): void
    {
        $activity = new Activity();
        $activity->setTitle('Test activité');
        $activity->setType('JDS');
        $activity->setStartAt(new \DateTimeImmutable('+1 week'));
        $activity->setStatus(Activity::STATUS_PUBLISHED);
        $this->em->persist($activity);
        $this->em->flush();

        $this->client->request('GET', '/admin/activites/' . $activity->getId() . '/modifier');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanOpenCarouselEdit(): void
    {
        $slide = new CarouselSlide();
        $slide->setTag('Tag');
        $slide->setTagColor('text-custom-orange');
        $slide->setTitle('Slide');
        $slide->setDate('AUJOURD\'HUI');
        $slide->setBtnText('GO');
        $slide->setBtnClass('bg-custom-orange group-hover:bg-orange-600 shadow-custom-orange/20');
        $slide->setPosition(0);
        $this->em->persist($slide);
        $this->em->flush();

        $this->client->request('GET', '/admin/carousel/' . $slide->getId() . '/modifier');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminCanOpenArticleEdit(): void
    {
        $article = new Article();
        $article->setTag('NEW');
        $article->setTitle('Article test');
        $article->setImage('placeholder.jpg');
        $article->setPosition(0);
        $this->em->persist($article);
        $this->em->flush();

        $this->client->request('GET', '/admin/articles/' . $article->getId() . '/modifier');
        $html = $this->client->getResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller="article-form"]');
        $this->assertSelectorExists('[data-action="click->article-form#showDetailPreview"]');
        $this->assertSelectorExists('[data-article-form-target="editor"]');
        $this->assertStringNotContainsString('function initQuill', $html);
        $this->assertStringNotContainsString('function initLivePreview', $html);
    }

    public function testAdminArticleNewUsesStimulusFormController(): void
    {
        $this->client->request('GET', '/admin/articles/nouveau');
        $html = $this->client->getResponse()->getContent();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller="article-form"]');
        $this->assertSelectorExists('[data-action="click->article-form#showDetailPreview"]');
        $this->assertSelectorExists('[data-article-form-target="editor"]');
        $this->assertStringNotContainsString('function initQuill', $html);
        $this->assertStringNotContainsString('function initLivePreview', $html);
    }

    public function testAdminCanOpenLudothequeEdit(): void
    {
        $game = new BoardGame();
        $game->setTitle('Catan');
        $game->setStatus(BoardGame::STATUS_AVAILABLE);
        $this->em->persist($game);
        $this->em->flush();

        $this->client->request('GET', '/admin/ludotheque/' . $game->getId() . '/modifier');

        $this->assertResponseIsSuccessful();
    }

    public function testAdminCsvExports(): void
    {
        $this->client->request('GET', '/admin/activites/export-csv');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/ludotheque/export-csv');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/utilisateurs/export-csv');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/newsletter/export');
        $this->assertResponseIsSuccessful();
    }

    public function testAdminNewsletterIndexWithSubscriber(): void
    {
        $sub = new NewsletterSubscriber();
        $sub->setEmail('news@example.com');
        $sub->setCreatedAtValue();
        $sub->confirm();
        $this->em->persist($sub);
        $this->em->flush();

        $this->client->request('GET', '/admin/newsletter');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('news@example.com', $this->client->getResponse()->getContent());
    }
}
