<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    public function testAllowsVerifiedNonSuspendedUser(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $user->setSuspended(false);

        $this->checker->checkPreAuth($user);
        $this->checker->checkPostAuth($user);

        $this->assertTrue(true);
    }

    public function testPreAuthDoesNotRevealSuspendedStatus(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $user->setSuspended(true);

        $this->checker->checkPreAuth($user);

        $this->assertTrue(true);
    }

    public function testPostAuthBlocksSuspendedUserWithGenericMessage(): void
    {
        $user = new User();
        $user->setIsVerified(true);
        $user->setSuspended(true);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Identifiants invalides.');

        $this->checker->checkPostAuth($user);
    }

    public function testPostAuthBlocksUnverifiedUserWithGenericMessage(): void
    {
        $user = new User();
        $user->setIsVerified(false);
        $user->setSuspended(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Identifiants invalides.');

        $this->checker->checkPostAuth($user);
    }

    public function testIgnoresNonAppUser(): void
    {
        $user = $this->createStub(UserInterface::class);

        $this->checker->checkPreAuth($user);
        $this->checker->checkPostAuth($user);

        $this->assertTrue(true);
    }
}
