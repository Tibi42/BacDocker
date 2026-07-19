<?php

namespace App\Tests\Unit;

use App\Entity\Activity;
use App\Notification\NewActivityProposalNotification;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;

class NewActivityProposalNotificationTest extends TestCase
{
    public function testSubjectContainsActivityTitle(): void
    {
        $activity = new Activity();
        $activity->setTitle('Soirée JDR');

        $notification = new NewActivityProposalNotification($activity, '/admin/activites');

        $this->assertSame('Nouvelle proposition d\'activité : Soirée JDR', $notification->getSubject());
    }

    public function testAsEmailMessageBuildsTemplatedEmail(): void
    {
        $activity = new Activity();
        $activity->setTitle('Soirée JDS');

        $notification = new NewActivityProposalNotification($activity, 'https://example.com/admin/activites');

        $recipient = $this->createStub(EmailRecipientInterface::class);
        $recipient->method('getEmail')->willReturn('admin@example.com');

        $message = $notification->asEmailMessage($recipient);
        $email = $message->getMessage();

        $this->assertInstanceOf(TemplatedEmail::class, $email);
        $this->assertSame('Nouvelle proposition en attente : Soirée JDS', $email->getSubject());
        $this->assertSame(['admin@example.com'], array_map(
            static fn ($a) => $a->getAddress(),
            $email->getTo()
        ));
        $this->assertSame('emails/activity_proposed_admin.html.twig', $email->getHtmlTemplate());
        $this->assertSame($activity, $email->getContext()['activity']);
        $this->assertSame('https://example.com/admin/activites', $email->getContext()['reviewUrl']);
    }
}
