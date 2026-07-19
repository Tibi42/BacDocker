<?php

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use App\Repository\UserRepository;

class WorkflowTest extends DatabaseWebTestCase
{
    public function testRegistrationCreatesUnverifiedUserAndRedirects(): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrf = $crawler->filter('#register-modal input[name="_csrf_token"]')->attr('value');
        $this->assertNotEmpty($csrf);

        $this->client->request('POST', '/register', [
            'email' => 'newbie@example.com',
            'username' => 'newbie42',
            'password' => 'ValidPassphrase!99',
            '_csrf_token' => $csrf,
        ]);

        $this->assertResponseRedirects();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'newbie@example.com']);
        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse($user->isVerified());
    }

    public function testNewsletterSubscribeConfirmAndUnsubscribe(): void
    {
        $crawler = $this->client->request('GET', '/');
        $csrf = $crawler->filter('form[action$="/newsletter/subscribe"] input[name="_token"]')->first()->attr('value');
        $this->assertNotEmpty($csrf);

        $this->client->request('POST', '/newsletter/subscribe', [
            'email' => 'abo@example.com',
            '_token' => $csrf,
        ]);

        $this->assertResponseRedirects();

        $subscriber = $this->em->getRepository(NewsletterSubscriber::class)
            ->findOneBy(['email' => 'abo@example.com']);
        $this->assertInstanceOf(NewsletterSubscriber::class, $subscriber);
        $this->assertSame(NewsletterSubscriber::STATUS_PENDING, $subscriber->getStatus());
        $token = $subscriber->getToken();
        $this->assertNotNull($token);

        $this->client->request('GET', '/newsletter/confirm/' . $token);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('confirmée', $this->client->getResponse()->getContent());

        $this->em->clear();
        $subscriber = $this->em->getRepository(NewsletterSubscriber::class)
            ->findOneBy(['email' => 'abo@example.com']);
        $this->assertSame(NewsletterSubscriber::STATUS_CONFIRMED, $subscriber->getStatus());

        $unsubscribeToken = $subscriber->getToken();
        $this->assertNotEmpty($unsubscribeToken);

        $this->client->request('GET', '/newsletter/unsubscribe/' . $unsubscribeToken);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Confirmer la désinscription', $this->client->getResponse()->getContent());

        $crawler = $this->client->getCrawler();
        $form = $crawler->filter('form[action*="unsubscribe"]')->form();
        $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Désinscription', $this->client->getResponse()->getContent());

        $this->em->clear();
        $subscriber = $this->em->getRepository(NewsletterSubscriber::class)
            ->findOneBy(['email' => 'abo@example.com']);
        $this->assertSame(NewsletterSubscriber::STATUS_UNSUBSCRIBED, $subscriber->getStatus());
    }

    public function testLoggedInUserCanOpenActivityProposalForm(): void
    {
        $user = $this->createUser('proposer@example.com', 'DevUserPass!12', ['ROLE_USER'], 'proposer');
        $this->login($user);

        $this->client->request('GET', '/activite/nouvelle');

        $this->assertResponseIsSuccessful();
    }

    public function testLoggedInUserCanOpenActivityRegistrationForm(): void
    {
        $user = $this->createUser('player@example.com', 'DevUserPass!12', ['ROLE_USER'], 'player');
        $activity = new Activity();
        $activity->setTitle('Soirée test');
        $activity->setType('JDS');
        $activity->setStartAt(new \DateTimeImmutable('+3 days'));
        $activity->setStatus(Activity::STATUS_PUBLISHED);
        $this->em->persist($activity);
        $this->em->flush();

        $this->login($user);
        $this->client->request('GET', '/activite/' . $activity->getId() . '/inscrire');

        $this->assertResponseIsSuccessful();
    }

    public function testArticleShowPage(): void
    {
        $article = new \App\Entity\Article();
        $article->setTag('TAG');
        $article->setTitle('Mon article');
        $article->setImage('img.jpg');
        $article->setContent('<p>Contenu</p>');
        $article->setPosition(1);
        $article->setActive(true);
        $this->em->persist($article);
        $this->em->flush();

        $this->client->request('GET', '/nouvelles/article/' . $article->getId());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Mon article', $this->client->getResponse()->getContent());
    }
}
