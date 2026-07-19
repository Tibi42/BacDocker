<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérificateur de compte utilisateur après authentification des identifiants.
 *
 * Les contrôles statut (suspendu / non vérifié) sont volontairement en
 * checkPostAuth : un attaquant sans mot de passe valide ne peut pas
 * énumérer l'existence ou l'état d'un compte.
 */
class UserChecker implements UserCheckerInterface
{
    private const GENERIC_INVALID = 'Identifiants invalides.';

    public function checkPreAuth(UserInterface $user): void
    {
        // Intentionnellement vide : ne pas révéler le statut avant le mot de passe.
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException(self::GENERIC_INVALID);
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(self::GENERIC_INVALID);
        }
    }
}
