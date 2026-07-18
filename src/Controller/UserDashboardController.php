<?php

namespace App\Controller;

use App\Entity\Inscription;
use App\Repository\ActivityRepository;
use App\Repository\InscriptionRepository;
use App\Validator\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Espace personnel de l'utilisateur connecté.
 *
 * Regroupe : consultation des inscriptions (à venir / passées),
 * changement de mot de passe, changement d'email, désinscription d'une
 * activité, gestion de l'abonnement newsletter et suppression du compte.
 *
 * Toutes les routes nécessitent ROLE_USER (attribut de classe).
 */
#[IsGranted('ROLE_USER')]
class UserDashboardController extends AbstractController
{
    /**
     * Page principale de l'espace utilisateur.
     *
     * Récupère les inscriptions de l'utilisateur (par email ET par nom d'utilisateur
     * pour couvrir les inscriptions manuelles admin), puis les sépare en deux listes :
     * activités à venir et activités passées.
     */
    #[Route('/mon-espace', name: 'app_user_dashboard')]
    public function index(InscriptionRepository $inscriptionRepository, ActivityRepository $activityRepository): Response
    {
        $user = $this->getUser();
        $email = $user->getEmail();

        // Inscriptions de l'utilisateur (par email uniquement — évite les collisions de noms)
        $inscriptions = $inscriptionRepository->createQueryBuilder('i')
            ->leftJoin('i.activity', 'a')
            ->addSelect('a')
            ->where('i.participantEmail = :email')
            ->setParameter('email', $email)
            ->orderBy('a.startAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Séparer inscriptions à venir / passées
        $now = new \DateTimeImmutable();
        $upcoming = [];
        $past = [];
        foreach ($inscriptions as $inscription) {
            $activity = $inscription->getActivity();
            if ($activity && $activity->getStartAt() >= $now) {
                $upcoming[] = $inscription;
            } else {
                $past[] = $inscription;
            }
        }

        return $this->render('user_dashboard/index.html.twig', [
            'upcoming' => $upcoming,
            'past' => $past,
        ]);
    }

    /**
     * Changement de mot de passe depuis l'espace utilisateur.
     *
     * Vérifie le mot de passe actuel, valide la longueur minimale (12 caractères)
     * et la correspondance entre le nouveau mot de passe et sa confirmation.
     */
    #[Route('/mon-espace/changer-mot-de-passe', name: 'app_user_change_password', methods: ['POST'])]
    public function changePassword(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator): Response
    {
        if (!$this->isCsrfTokenValid('change_password', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user = $this->getUser();
        $currentPassword = (string) $request->request->get('current_password', '');
        $newPassword = (string) $request->request->get('new_password', '');
        $confirmPassword = (string) $request->request->get('confirm_password', '');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        foreach ($validator->validate($newPassword, PasswordPolicy::constraints()) as $violation) {
            $this->addFlash('error', $violation->getMessage());
            return $this->redirectToRoute('app_user_dashboard');
        }

        if ($newPassword !== $confirmPassword) {
            $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $em->flush();

        $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

        return $this->redirectToRoute('app_user_dashboard');
    }

    /**
     * Changement d'adresse email depuis l'espace utilisateur.
     *
     * Exige le mot de passe actuel. Le nouvel email doit être confirmé
     * via un lien envoyé à la nouvelle adresse avant d'être appliqué.
     */
    #[Route('/mon-espace/changer-email', name: 'app_user_change_email', methods: ['POST'])]
    public function changeEmail(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer,
    ): Response {
        if (!$this->isCsrfTokenValid('change_email', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user = $this->getUser();
        $currentPassword = (string) $request->request->get('current_password', '');
        $newEmail = trim((string) $request->request->get('new_email', ''));

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Veuillez saisir une adresse email valide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        if ($newEmail === $user->getEmail()) {
            $this->addFlash('warning', 'C\'est déjà votre adresse email actuelle.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $genericChangeEmailFlash = 'Si cette adresse est disponible, un email de confirmation vous a été envoyé.';

        $existing = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $newEmail]);
        if ($existing) {
            try {
                $alert = (new TemplatedEmail())
                    ->from('noreply@laboiteachimere.fr')
                    ->to((string) $user->getEmail())
                    ->subject('Tentative de changement d\'adresse email')
                    ->htmlTemplate('emails/email_change_attempt_alert.html.twig')
                    ->context(['username' => $user->getUsername()]);
                $mailer->send($alert);
            } catch (\Throwable) {
            }
            $this->addFlash('success', $genericChangeEmailFlash);
            return $this->redirectToRoute('app_user_dashboard');
        }

        // Token dédié + email en attente dans une colonne séparée (TTL 15 minutes)
        $token = bin2hex(random_bytes(32));
        $user->setPendingEmail($newEmail);
        $user->setEmailVerificationToken($token);
        $user->setEmailVerificationExpiresAt(new \DateTimeImmutable('+15 minutes'));
        $em->flush();

        $confirmUrl = $this->generateUrl('app_confirm_email_change', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from('noreply@laboiteachimere.fr')
            ->to($newEmail)
            ->subject('Confirmez votre nouvelle adresse email')
            ->htmlTemplate('emails/email_change_confirm.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'confirmUrl' => $confirmUrl,
                'newEmail' => $newEmail,
            ]);
        $mailer->send($email);

        $this->addFlash('success', 'Un email de confirmation a été envoyé à ' . $newEmail . '. Le lien est valable 15 minutes.');

        return $this->redirectToRoute('app_user_dashboard');
    }

    #[Route('/mon-espace/confirmer-email/{token}', name: 'app_confirm_email_change', methods: ['GET'])]
    public function confirmEmailChange(string $token, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $storedToken = $user->getEmailVerificationToken() ?? '';

        if ($storedToken === '' || !hash_equals($storedToken, $token)) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou a expiré.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        if ($user->isEmailVerificationTokenExpired()) {
            $user->clearEmailVerificationState();
            $em->flush();
            $this->addFlash('error', 'Ce lien de confirmation a expiré. Veuillez relancer le changement d\'email.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $newEmail = $user->getPendingEmail();
        if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $existing = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $newEmail]);
        if ($existing && $existing->getId() !== $user->getId()) {
            $this->addFlash('error', 'Cette adresse email est déjà utilisée.');
            $user->clearEmailVerificationState();
            $em->flush();
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user->setEmail($newEmail);
        $user->clearEmailVerificationState();
        $em->flush();

        $this->addFlash('success', 'Votre adresse email a été mise à jour.');

        return $this->redirectToRoute('app_user_dashboard');
    }

    /**
     * Désinscription d'une activité (ou suppression si l'utilisateur en est le créateur).
     *
     * Si l'utilisateur est le créateur (proposedBy), l'activité entière est supprimée
     * et tous les participants inscrits reçoivent un email de notification d'annulation.
     * Sinon, seule l'inscription de l'utilisateur courant est supprimée.
     */
    #[Route('/mon-espace/desinscription/{id}', name: 'app_user_unregister', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unregister(Inscription $inscription, Request $request, EntityManagerInterface $em, InscriptionRepository $inscriptionRepository, MailerInterface $mailer): Response
    {
        if (!$this->isCsrfTokenValid('unregister' . $inscription->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user = $this->getUser();
        // Vérifier que l'inscription appartient bien à cet utilisateur (email uniquement)
        if ($inscription->getParticipantEmail() !== $user->getEmail()) {
            $this->addFlash('error', 'Cette inscription ne vous appartient pas.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $activity = $inscription->getActivity();
        $activityTitle = $activity?->getTitle() ?? 'activité';

        if ($activity && $activity->getProposedBy()?->getId() === $this->getUser()->getId()) {
            // Récupérer tous les inscrits avant suppression pour leur envoyer un email
            $inscriptions = $inscriptionRepository->findBy(['activity' => $activity]);
            $siteUrl = $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

            foreach ($inscriptions as $i) {
                // Ne pas envoyer au créateur lui-même
                if ($i->getParticipantEmail() === $this->getUser()->getEmail()) {
                    continue;
                }
                try {
                    $email = (new TemplatedEmail())
                        ->from('noreply@laboiteachimere.fr')
                        ->to($i->getParticipantEmail())
                        ->subject('Événement annulé : ' . $activityTitle)
                        ->htmlTemplate('emails/activity_cancelled.html.twig')
                        ->context([
                            'participantName' => $i->getParticipantName(),
                            'activityTitle'   => $activityTitle,
                            'activityType'    => $activity->getType() ?? 'Événement',
                            'activityDate'    => $activity->getStartAt()?->format('d/m/Y') ?? '',
                            'activityLocation' => $activity->getLocation(),
                            'siteUrl'         => $siteUrl,
                        ]);
                    $mailer->send($email);
                } catch (\Throwable) {
                    // Ne pas bloquer la suppression si un email échoue
                }
            }

            $em->remove($activity);
            $em->flush();
            $this->addFlash('success', 'L\'événement « ' . $activityTitle . ' » a été supprimé et les participants ont été notifiés.');
        } else {
            $em->remove($inscription);
            $em->flush();
            $this->addFlash('success', 'Vous êtes désinscrit de « ' . $activityTitle . ' ».');
        }

        return $this->redirectToRoute('app_user_dashboard');
    }

    /**
     * Désabonnement de la newsletter depuis l'espace utilisateur.
     *
     * Met le flag newsletterOptIn à false sur l'entité User.
     */
    #[Route('/mon-espace/newsletter', name: 'app_user_newsletter', methods: ['POST'])]
    public function newsletter(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('newsletter_toggle', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user = $this->getUser();
        $user->setNewsletterOptIn(false);
        $em->flush();

        $this->addFlash('success', 'Vous avez été désabonné de la newsletter.');

        return $this->redirectToRoute('app_user_dashboard');
    }

    /**
     * Suppression du compte utilisateur courant.
     *
     * Interdit aux administrateurs (ils doivent passer par le back-office).
     * La session est invalidée et le token de sécurité effacé avant la suppression
     * en base pour éviter tout accès résiduel.
     */
    #[Route('/mon-espace/supprimer', name: 'app_user_delete_account', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Les administrateurs ne peuvent pas supprimer leur compte depuis cette page.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        if (!$this->isCsrfTokenValid('delete_my_account', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        $user = $this->getUser();
        $currentPassword = (string) $request->request->get('current_password', '');

        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Mot de passe incorrect. La suppression du compte a été annulée.');
            return $this->redirectToRoute('app_user_dashboard');
        }

        // Invalider la session avant suppression
        $request->getSession()->invalidate();
        $this->container->get('security.token_storage')->setToken(null);

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'Votre compte a été supprimé.');

        return $this->redirectToRoute('app_home');
    }
}
