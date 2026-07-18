<?php

namespace App\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Util\RateLimitKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/newsletter')]
/**
 * Contrôleur de gestion de la newsletter (double opt-in).
 *
 * Flux complet :
 *  1. POST /newsletter/subscribe → validation, rate limiting, envoi du mail de confirmation.
 *  2. GET  /newsletter/confirm/{token} → confirmation de l'abonnement.
 *  3. GET  /newsletter/unsubscribe/{token} → désinscription via le lien dans les emails.
 *
 * Gère également les ré-abonnements (status UNSUBSCRIBED → PENDING) et le
 * renvoi du mail de confirmation (status PENDING).
 */
final class NewsletterController extends AbstractController
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $repository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/subscribe', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        MailerInterface $mailer,
        RateLimiterFactoryInterface $newsletterSubscribeLimiter,
    ): Response
    {
        // CSRF protection
        if (!$this->isCsrfTokenValid('newsletter_subscribe', $request->request->get('_token'))) {
            $this->addFlash('newsletter_error', 'Jeton de sécurité invalide, veuillez réessayer.');
            return $this->redirectToReferer($request);
        }

        // Rate limiting by IP + email
        $email = trim((string) $request->request->get('email'));
        $limiter = $newsletterSubscribeLimiter->create(RateLimitKey::forIpAndIdentifier(
            $request->getClientIp() ?? 'unknown',
            $email !== '' ? $email : 'anonymous',
        ));
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('newsletter_error', 'Trop de tentatives. Veuillez réessayer dans quelques minutes.');
            return $this->redirectToReferer($request);
        }

        $genericFlash = 'Si cette adresse est éligible, un email de confirmation vous a été envoyé.';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('newsletter_error', 'Veuillez entrer une adresse email valide.');
            return $this->redirectToReferer($request);
        }

        $existing = $this->repository->findByEmail($email);

        if ($existing) {
            if ($existing->getStatus() === NewsletterSubscriber::STATUS_CONFIRMED) {
                $this->addFlash('newsletter_success', $genericFlash);
                return $this->redirectToReferer($request);
            }

            if ($existing->getStatus() === NewsletterSubscriber::STATUS_UNSUBSCRIBED) {
                $existing->setStatus(NewsletterSubscriber::STATUS_PENDING);
                $existing->setUnsubscribedAt(null);
                $existing->regenerateToken();
                $this->entityManager->flush();
                $this->sendConfirmationEmail($mailer, $existing);
                $this->addFlash('newsletter_success', $genericFlash);
                return $this->redirectToReferer($request);
            }

            // Already pending — resend confirmation
            $existing->regenerateToken();
            $this->entityManager->flush();
            $this->sendConfirmationEmail($mailer, $existing);
            $this->addFlash('newsletter_success', $genericFlash);
            return $this->redirectToReferer($request);
        }

        $subscriber = new NewsletterSubscriber();
        $subscriber->setEmail($email);
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $this->sendConfirmationEmail($mailer, $subscriber);

        $this->addFlash('newsletter_success', $genericFlash);
        return $this->redirectToReferer($request);
    }

    #[Route('/confirm/{token}', name: 'app_newsletter_confirm', methods: ['GET'])]
    public function confirm(string $token): Response
    {
        $subscriber = $this->repository->findByToken($token);

        if (!$subscriber) {
            return $this->render('newsletter/result.html.twig', [
                'title' => 'Lien invalide',
                'message' => 'Ce lien de confirmation est invalide ou a expiré.',
                'success' => false,
            ]);
        }

        if ($subscriber->getStatus() === NewsletterSubscriber::STATUS_PENDING && $subscriber->isTokenExpired()) {
            return $this->render('newsletter/result.html.twig', [
                'title' => 'Lien expiré',
                'message' => 'Ce lien de confirmation a expiré. Veuillez vous réinscrire à la newsletter.',
                'success' => false,
            ]);
        }

        if ($subscriber->getStatus() === NewsletterSubscriber::STATUS_CONFIRMED) {
            return $this->render('newsletter/result.html.twig', [
                'title' => 'Déjà confirmé',
                'message' => 'Votre inscription est déjà confirmée.',
                'success' => true,
            ]);
        }

        $subscriber->confirm();
        $this->entityManager->flush();

        return $this->render('newsletter/result.html.twig', [
            'title' => 'Inscription confirmée',
            'message' => 'Votre inscription à la newsletter de La Boîte à Chimère est confirmée. Merci !',
            'success' => true,
        ]);
    }

    #[Route('/unsubscribe/{token}', name: 'app_newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribe(string $token): Response
    {
        $subscriber = $this->repository->findByToken($token);

        if (!$subscriber) {
            return $this->render('newsletter/result.html.twig', [
                'title' => 'Lien invalide',
                'message' => 'Ce lien de désinscription est invalide ou a expiré.',
                'success' => false,
            ]);
        }

        $subscriber->unsubscribe();
        $this->entityManager->flush();

        return $this->render('newsletter/result.html.twig', [
            'title' => 'Désinscription confirmée',
            'message' => 'Vous avez été désinscrit de la newsletter. Vous ne recevrez plus nos communications.',
            'success' => true,
        ]);
    }

    /**
     * Envoie l'email de confirmation avec le lien unique contenant le token.
     */
    private function sendConfirmationEmail(MailerInterface $mailer, NewsletterSubscriber $subscriber): void
    {
        $confirmUrl = $this->generateUrl('app_newsletter_confirm', [
            'token' => $subscriber->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('newsletter@laboiteachimere.fr')
            ->to($subscriber->getEmail())
            ->subject('Confirmez votre inscription à la newsletter - La Boîte à Chimère')
            ->htmlTemplate('emails/newsletter_confirmation.html.twig')
            ->context([
                'confirmUrl' => $confirmUrl,
            ]);

        $mailer->send($email);
    }

    /**
     * Redirige vers la page précédente (Referer) si elle appartient au même domaine,
     * sinon redirige vers la page d'accueil (sécurité anti-open-redirect).
     */
    private function redirectToReferer(Request $request): Response
    {
        $referer = $request->headers->get('referer', '');
        if ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }
        return $this->redirectToRoute('app_home');
    }
}
