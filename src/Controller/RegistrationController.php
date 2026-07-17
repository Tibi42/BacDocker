<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur d'inscription des nouveaux utilisateurs.
 *
 * Traite uniquement les requêtes POST /register soumises depuis la modale
 * d'inscription de la page d'accueil. Après validation et création du
 * compte, un email de confirmation est envoyé ; la connexion n'est possible
 * qu'après clic sur le lien de vérification.
 */
final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        MailerInterface $mailer,
        RateLimiterFactoryInterface $registrationLimiter,
    ): Response {
        $limiter = $registrationLimiter->create($request->getClientIp() ?? 'unknown');
        if (!$limiter->consume()->isAccepted()) {
            $this->addFlash('register_error', 'Trop de tentatives. Veuillez réessayer dans quelques minutes.');
            return $this->redirectToRoute('app_home', ['open' => 'register']);
        }

        $email = trim((string) $request->request->get('email'));
        $username = trim((string) $request->request->get('username'));
        $password = (string) $request->request->get('password');
        $csrfToken = (string) $request->request->get('_csrf_token');

        if (!$this->isCsrfTokenValid('register', $csrfToken)) {
            $this->addFlash('register_error', 'Jeton de sécurité invalide. Veuillez réessayer.');
            return $this->redirectToRoute('app_home', ['open' => 'register']);
        }

        $errors = [];
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Veuillez entrer une adresse email valide.';
        }
        if (!$username || mb_strlen($username) < 3 || !preg_match('/^[\w\-.\ ]+$/u', $username)) {
            $errors[] = 'Le nom d\'utilisateur doit faire au moins 3 caractères et ne contenir que des lettres, chiffres, espaces, tirets, points et underscores.';
        }
        if (mb_strlen($password) < 12) {
            $errors[] = 'Le mot de passe doit faire au moins 12 caractères.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                $this->addFlash('register_error', $error);
            }
            return $this->redirectToRoute('app_home', ['open' => 'register', 'email' => $email]);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            $this->addFlash('register_error', 'Cette adresse email est déjà utilisée.');
            return $this->redirectToRoute('app_home', ['open' => 'login']);
        }
        if ($userRepository->findOneBy(['username' => $username])) {
            $this->addFlash('register_error', 'Ce nom d\'utilisateur est déjà pris.');
            return $this->redirectToRoute('app_home', ['open' => 'register', 'email' => $email]);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(false);
        $user->regenerateEmailVerificationToken();

        $entityManager->persist($user);
        $entityManager->flush();

        $confirmUrl = $this->generateUrl('app_verify_email', [
            'token' => $user->getEmailVerificationToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $welcomeEmail = (new TemplatedEmail())
            ->from('noreply@laboiteachimere.fr')
            ->to($user->getEmail())
            ->subject('Confirmez votre inscription — La Boîte à Chimère')
            ->htmlTemplate('emails/registration_confirm.html.twig')
            ->context([
                'username' => $user->getUsername(),
                'confirmUrl' => $confirmUrl,
            ]);
        $mailer->send($welcomeEmail);

        $admins = $userRepository->findAdmins();
        if ($admins) {
            $adminUrl = $this->generateUrl('admin', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $adminNotif = (new TemplatedEmail())
                ->from('noreply@laboiteachimere.fr')
                ->subject('Nouvelle inscription : ' . $user->getUsername())
                ->htmlTemplate('emails/registration_admin_notify.html.twig')
                ->context([
                    'username' => $user->getUsername(),
                    'userEmail' => $user->getEmail(),
                    'registeredAt' => new \DateTimeImmutable(),
                    'adminUrl' => $adminUrl,
                ]);
            foreach ($admins as $admin) {
                $adminNotif->addTo($admin->getEmail());
            }
            $mailer->send($adminNotif);
        }

        $this->addFlash('success', 'Compte créé ! Un email de confirmation vous a été envoyé. Cliquez sur le lien pour activer votre compte.');

        return $this->redirectToRoute('app_home', ['open' => 'login']);
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $token,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $userRepository->findOneBy(['emailVerificationToken' => $token]);

        if (!$user || $user->isVerified()) {
            $this->addFlash('error', 'Ce lien de confirmation est invalide ou a déjà été utilisé.');
            return $this->redirectToRoute('app_home', ['open' => 'login']);
        }

        $user->setIsVerified(true);
        $user->setEmailVerificationToken(null);
        $entityManager->flush();

        $this->addFlash('success', 'Votre email est confirmé. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_home', ['open' => 'login']);
    }
}
