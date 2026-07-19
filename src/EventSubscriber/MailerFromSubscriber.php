<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Force l'expéditeur configuré (doit correspondre au compte SMTP OVH en prod).
 */
final class MailerFromSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $fromEmail,
        #[Autowire('%env(default:app_mailer_from_name:MAILER_FROM_NAME)%')]
        private readonly string $fromName,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof Email) {
            return;
        }

        $message->from(new Address($this->fromEmail, $this->fromName));
    }
}
