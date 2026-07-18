<?php

namespace App\Validator;

/**
 * Règles de validation partagées pour email et nom d'utilisateur.
 */
final class UserAccountRules
{
    public static function emailError(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse email invalide.';
        }

        return null;
    }

    public static function usernameError(?string $username): ?string
    {
        $username = trim((string) $username);
        if ($username === '' || mb_strlen($username) < 3) {
            return 'Le nom d\'utilisateur doit faire au moins 3 caractères.';
        }
        if (!preg_match('/^[\w\-.\ ]+$/u', $username)) {
            return 'Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, espaces, tirets, points et underscores.';
        }

        return null;
    }

    public static function isValidEmail(?string $email): bool
    {
        return self::emailError($email) === null;
    }

    public static function isValidUsername(?string $username): bool
    {
        return self::usernameError($username) === null;
    }
}
