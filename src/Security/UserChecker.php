<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérificateur de compte utilisateur avant authentification.
 *
 * Appelé par le firewall Symfony lors du processus de connexion.
 * Empêche la connexion si le compte est suspendu (User::$suspended = true)
 * et retourne un message explicite à l'utilisateur.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu. Contactez un administrateur.'
            );
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Veuillez confirmer votre adresse email avant de vous connecter. Vérifiez votre boîte de réception.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
