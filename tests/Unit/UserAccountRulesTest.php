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
    }

    public function testAcceptsValidUsername(): void
    {
        $this->assertNull(UserAccountRules::usernameError('Joueur_42'));
    }

    public function testPrivilegedAccountDetection(): void
    {
        $admin = new User();
        $admin->setEmail('a@example.com');
        $admin->setUsername('admin');
        $admin->setRoles(['ROLE_ADMIN']);

        $user = new User();
        $user->setEmail('u@example.com');
        $user->setUsername('user');
        $user->setRoles([]);

        $this->assertTrue(AdminUserAuthorization::isPrivilegedAccount($admin));
        $this->assertFalse(AdminUserAuthorization::isPrivilegedAccount($user));
    }
}
