<?php

namespace App\Security;

use App\Entity\User;

/**
 * Règles d'autorisation pour la gestion des comptes admin depuis le back-office.
 */
final class AdminUserAuthorization
{
    public static function isPrivilegedAccount(User $user): bool
    {
        $roles = $user->getRoles();

        return \in_array('ROLE_ADMIN', $roles, true)
            || \in_array('ROLE_SUPER_ADMIN', $roles, true);
    }
}
