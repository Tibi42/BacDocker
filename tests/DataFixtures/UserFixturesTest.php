<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixturesTest extends TestCase
{
    public function testRefusesProductionEnvironment(): void
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $manager = $this->createStub(ObjectManager::class);

        $fixtures = new UserFixtures($hasher, 'prod');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('production');

        $fixtures->load($manager);
    }

    public function testLoadsUsersInDev(): void
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $persisted = [];
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $manager->expects($this->once())->method('flush');

        $fixtures = new UserFixtures($hasher, 'test');
        $fixtures->load($manager);

        $this->assertNotEmpty($persisted);
        $this->assertContainsOnlyInstancesOf(User::class, $persisted);
        $emails = array_map(static fn (User $u) => $u->getEmail(), $persisted);
        $this->assertContains('superadmin@example.com', $emails);
        $this->assertContains('admin@example.com', $emails);
        $this->assertContains('user@example.com', $emails);
        $this->assertNotContains('guillaume.Pecquet@gmail.com', $emails);
    }

    public function testBelongsToUserGroup(): void
    {
        $this->assertSame(['user'], UserFixtures::getGroups());
    }
}
