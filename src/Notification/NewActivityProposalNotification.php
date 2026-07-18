<?php

namespace App\Notification;

use App\Entity\Activity;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

/**
 * Notification envoyée aux administrateurs quand un membre propose une nouvelle activité.
 *
 * Implémente EmailNotificationInterface pour être envoyée via le composant
 * Notifier de Symfony. Le mail contient les détails de l'activité et un lien
 * direct vers la liste admin pour l'approuver ou la rejeter.
 */
class NewActivityProposalNotification extends Notification implements EmailNotificationInterface
{
    public function __construct(
        private readonly Activity $activity,
        private readonly string $reviewUrl,
    ) {
        parent::__construct('Nouvelle proposition d\'activité : ' . $activity->getTitle());
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, ?string $transport = null): EmailMessage
    {
        $email = (new TemplatedEmail())
            ->from(new Address('noreply@laboiteachimere.fr', 'La Boîte à Chimère'))
            ->to($recipient->getEmail())
            ->subject('Nouvelle proposition en attente : ' . $this->activity->getTitle())
            ->htmlTemplate('emails/activity_proposed_admin.html.twig')
            ->context([
                'activity' => $this->activity,
                'reviewUrl' => $this->reviewUrl,
            ]);

        return new EmailMessage($email);
    }
}
