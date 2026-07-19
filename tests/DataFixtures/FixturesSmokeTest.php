<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\ActivityFixtures;
use App\DataFixtures\BoardGameFixtures;
use App\DataFixtures\CarouselSlideFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\CarouselSlide;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;

class FixturesSmokeTest extends TestCase
{
    public function testCarouselSlideFixturesPersistSlides(): void
    {
        $persisted = [];
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $manager->expects($this->once())->method('flush');

        (new CarouselSlideFixtures())->load($manager);

        $this->assertContainsOnlyInstancesOf(CarouselSlide::class, $persisted);
        $this->assertGreaterThanOrEqual(3, count($persisted));
    }

    public function testActivityFixturesDependsOnUsers(): void
    {
        $fixtures = new ActivityFixtures();

        $this->assertSame([UserFixtures::class], $fixtures->getDependencies());
    }

    public function testBoardGameFixturesDependsOnUsers(): void
    {
        $fixtures = new BoardGameFixtures(sys_get_temp_dir() . '/bac_bg_images');

        $this->assertSame([UserFixtures::class], $fixtures->getDependencies());
    }

    public function testActivityFixturesLoadsWithUsers(): void
    {
        $user = new User();
        $user->setEmail('u@example.com');
        $user->setUsername('u');
        $user->setPassword('x');

        $repo = $this->createStub(\Doctrine\Persistence\ObjectRepository::class);
        $repo->method('findAll')->willReturn([$user]);

        $persisted = [];
        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getRepository')->willReturn($repo);
        $manager->expects($this->atLeastOnce())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $manager->expects($this->once())->method('flush');

        (new ActivityFixtures())->load($manager);

        $this->assertNotEmpty($persisted);
    }
}
