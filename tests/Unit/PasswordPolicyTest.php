<?php

namespace App\Tests\Unit;

use App\Validator\PasswordPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class PasswordPolicyTest extends TestCase
{
    public function testRejectsRepeatedSingleCharacterPassword(): void
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate('aaaaaaaaaaaa', PasswordPolicy::constraints());

        $this->assertGreaterThan(0, $violations->count());
    }

    public function testAcceptsStrongPasswordShape(): void
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate('ValidPassphrase!99', PasswordPolicy::constraints());

        $messages = [];
        foreach ($violations as $violation) {
            if (!str_contains($violation->getMessage(), 'compromis')) {
                $messages[] = $violation->getMessage();
            }
        }

        $this->assertSame([], $messages);
    }
}
