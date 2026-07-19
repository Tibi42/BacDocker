<?php

namespace App\Tests\Unit;

use App\EventSubscriber\MailerFromSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class MailerFromSubscriberTest extends TestCase
{
    public function testSubscribesToMessageEvent(): void
    {
        $events = MailerFromSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(MessageEvent::class, $events);
        $this->assertSame(['onMessage', 100], $events[MessageEvent::class]);
    }

    public function testForcesConfiguredFromAddress(): void
    {
        $subscriber = new MailerFromSubscriber('noreply@example.com', 'La Boîte');
        $email = (new Email())
            ->from('other@example.com')
            ->to('user@example.com')
            ->text('hello');

        $event = new MessageEvent(
            $email,
            Envelope::create($email),
            'null://null'
        );

        $subscriber->onMessage($event);

        $from = $email->getFrom();
        $this->assertCount(1, $from);
        $this->assertSame('noreply@example.com', $from[0]->getAddress());
        $this->assertSame('La Boîte', $from[0]->getName());
    }

    public function testIgnoresNonEmailMessages(): void
    {
        $subscriber = new MailerFromSubscriber('noreply@example.com', 'La Boîte');
        $message = new RawMessage('raw');
        $envelope = new Envelope(
            new Address('a@example.com'),
            [new Address('b@example.com')]
        );

        $event = new MessageEvent($message, $envelope, 'null://null');

        $subscriber->onMessage($event);

        $this->assertSame('raw', $event->getMessage()->toString());
    }
}
