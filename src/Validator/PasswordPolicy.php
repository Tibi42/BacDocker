<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Contraintes partagées pour tous les flux de mot de passe.
 */
final class PasswordPolicy
{
    /**
     * @return list<object>
     */
    public static function constraints(bool $required = true): array
    {
        $constraints = [];

        if ($required) {
            $constraints[] = new NotBlank(message: 'Le mot de passe est obligatoire.');
        }

        $constraints[] = new Length(
            min: 12,
            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
            max: 4096,
        );
        $constraints[] = new NotCompromisedPassword(
            message: 'Ce mot de passe a été compromis dans une fuite de données. Choisissez-en un autre.',
            skipOnError: true,
        );
        $constraints[] = new Callback(self::rejectTrivialPassword(...));

        return $constraints;
    }

    public static function rejectTrivialPassword(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_string($value) || $value === '') {
            return;
        }

        if (preg_match('/^(.)\1+$/u', $value)) {
            $context->buildViolation('Le mot de passe ne peut pas être composé d\'un seul caractère répété.')
                ->addViolation();
        }
    }
}
