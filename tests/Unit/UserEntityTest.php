<?php

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserEntityTest extends TestCase
{
    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testGetUserIdentifierUsesEmail(): void
    {
        $user = new User();
        $user->setEmail('bob@example.com');

        $this->assertSame('bob@example.com', $user->getUserIdentifier());
    }

    public function testDefaultSuspendedIsFalse(): void
    {
        $user = new User();

        $this->assertFalse($user->isSuspended());
    }

    public function testSetSuspended(): void
    {
        $user = new User();
        $user->setSuspended(true);

        $this->assertTrue($user->isSuspended());
    }

    public function testUsernameSetterAndGetter(): void
    {
        $user = new User();
        $user->setUsername('chimere42');

        $this->assertSame('chimere42', $user->getUsername());
    }

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->eraseCredentials();

        $this->assertTrue(true);
    }

    public function testSetPasswordAndGetPassword(): void
    {
        $user = new User();
        $user->setPassword('hashed_pwd');

        $this->assertSame('hashed_pwd', $user->getPassword());
    }

    public function testDefaultIsVerifiedIsTrue(): void
    {
        $user = new User();

        $this->assertTrue($user->isVerified());
    }

    public function testSetIsVerified(): void
    {
        $user = new User();
        $user->setIsVerified(false);

        $this->assertFalse($user->isVerified());
    }

    public function testNewsletterOptInDefaultAndSetter(): void
    {
        $user = new User();

        $this->assertTrue($user->isNewsletterOptIn());

        $user->setNewsletterOptIn(false);

        $this->assertFalse($user->isNewsletterOptIn());
    }

    public function testPendingEmailAndVerificationToken(): void
    {
        $user = new User();
        $expires = new \DateTimeImmutable('+1 day');

        $user->setPendingEmail('new@example.com');
        $user->setEmailVerificationToken('token-abc');
        $user->setEmailVerificationExpiresAt($expires);

        $this->assertSame('new@example.com', $user->getPendingEmail());
        $this->assertSame('token-abc', $user->getEmailVerificationToken());
        $this->assertSame($expires, $user->getEmailVerificationExpiresAt());
    }
}

