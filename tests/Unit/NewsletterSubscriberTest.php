<?php

namespace App\Tests\Unit;

use App\Entity\NewsletterSubscriber;
use PHPUnit\Framework\TestCase;

class NewsletterSubscriberTest extends TestCase
{
    public function testDefaultStatusIsPending(): void
    {
        $subscriber = new NewsletterSubscriber();

        $this->assertSame(NewsletterSubscriber::STATUS_PENDING, $subscriber->getStatus());
    }

    public function testSetEmail(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->setEmail('membre@example.com');

        $this->assertSame('membre@example.com', $subscriber->getEmail());
    }

    public function testSetCreatedAtValueGeneratesTokenAndCreatedAt(): void
    {
        $subscriber = new NewsletterSubscriber();

        $subscriber->setCreatedAtValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $subscriber->getCreatedAt());
        $this->assertNotNull($subscriber->getToken());
        $this->assertSame(64, strlen($subscriber->getToken()));
        $this->assertInstanceOf(\DateTimeImmutable::class, $subscriber->getTokenExpiresAt());
        $this->assertFalse($subscriber->isTokenExpired());
    }

    public function testRegenerateTokenChangesToken(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->regenerateToken();
        $first = $subscriber->getToken();

        $subscriber->regenerateToken();

        $this->assertNotSame($first, $subscriber->getToken());
        $this->assertSame(64, strlen($subscriber->getToken()));
    }

    public function testIsTokenExpiredWhenInPast(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->setTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $this->assertTrue($subscriber->isTokenExpired());
    }

    public function testConfirmSetsStatusAndConfirmedAt(): void
    {
        $subscriber = new NewsletterSubscriber();
        $subscriber->regenerateToken();

        $subscriber->confirm();

        $this->assertSame(NewsletterSubscriber::STATUS_CONFIRMED, $subscriber->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $subscriber->getConfirmedAt());
        $this->assertNull($subscriber->getTokenExpiresAt());
    }

    public function testUnsubscribeSetsStatusAndUnsubscribedAt(): void
    {
        $subscriber = new NewsletterSubscriber();

        $subscriber->unsubscribe();

        $this->assertSame(NewsletterSubscriber::STATUS_UNSUBSCRIBED, $subscriber->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $subscriber->getUnsubscribedAt());
    }
}
