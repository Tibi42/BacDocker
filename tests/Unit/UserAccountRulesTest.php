<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\AdminUserAuthorization;
use App\Validator\UserAccountRules;
use PHPUnit\Framework\TestCase;

class UserAccountRulesTest extends TestCase
{
    public function testRejectsInvalidEmail(): void
    {
        $this->assertNotNull(UserAccountRules::emailError('not-an-email'));
        $this->assertFalse(UserAccountRules::isValidEmail('not-an-email'));
        $this->assertFalse(UserAccountRules::isValidEmail(''));
        $this->assertFalse(UserAccountRules::isValidEmail(null));
    }

    public function testAcceptsValidEmail(): void
    {
        $this->assertNull(UserAccountRules::emailError('ok@example.com'));
        $this->assertTrue(UserAccountRules::isValidEmail('ok@example.com'));
    }

    public function testAcceptsValidUsername(): void
    {
        $this->assertNull(UserAccountRules::usernameError('Joueur_42'));
        $this->assertTrue(UserAccountRules::isValidUsername('Joueur_42'));
    }

    public function testRejectsShortOrInvalidUsername(): void
    {
        $this->assertNotNull(UserAccountRules::usernameError('ab'));
        $this->assertNotNull(UserAccountRules::usernameError('bad!name'));
        $this->assertFalse(UserAccountRules::isValidUsername('ab'));
    }

    public function testPrivilegedAccountDetection(): void
    {
        $admin = new User();
        $admin->setEmail('a@example.com');
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);

        $super = new User();
        $super->setEmail('s@example.com');
        $super->setUsername('super');
        $super->setRoles(['ROLE_SUPER_ADMIN']);

        $user = new User();
        $user->setEmail('u@example.com');
        $user->setUsername('user');
        $user->setRoles([]);

        $this->assertTrue(AdminUserAuthorization::isPrivilegedAccount($admin));
        $this->assertTrue(AdminUserAuthorization::isPrivilegedAccount($super));
        $this->assertFalse(AdminUserAuthorization::isPrivilegedAccount($user));
    }
}
