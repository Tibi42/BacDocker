<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LoadFixturesCommandTest extends KernelTestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        if (!self::$schemaReady) {
            $meta = $em->getMetadataFactory()->getAllMetadata();
            $tool = new SchemaTool($em);
            $tool->dropSchema($meta);
            $tool->createSchema($meta);
            self::$schemaReady = true;
        } else {
            $conn = $em->getConnection();
            $conn->executeStatement('PRAGMA foreign_keys = OFF');
            foreach ($em->getMetadataFactory()->getAllMetadata() as $m) {
            $conn->executeStatement('DELETE FROM ' . $conn->quoteSingleIdentifier($m->getTableName()));
            }
            $conn->executeStatement('PRAGMA foreign_keys = ON');
            $em->clear();
        }
    }

    public function testLoadFixturesCreatesUsers(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('load-fixtures');
        $tester = new CommandTester($command);

        $status = $tester->execute([]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('admin@example.com', $tester->getDisplay());

        $repo = static::getContainer()->get(UserRepository::class);
        $this->assertInstanceOf(User::class, $repo->findOneBy(['email' => 'admin@example.com']));
        $this->assertInstanceOf(User::class, $repo->findOneBy(['email' => 'user@example.com']));
        $this->assertInstanceOf(User::class, $repo->findOneBy(['email' => 'superadmin@example.com']));
    }

    public function testLoadFixturesIsIdempotent(): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('load-fixtures'));

        $tester->execute([]);
        $tester->execute([]);

        $this->assertStringContainsString('existe déjà', $tester->getDisplay());
        $this->assertCount(
            3,
            static::getContainer()->get(UserRepository::class)->findAll()
        );
    }
}
