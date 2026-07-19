<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class DatabaseWebTestCase extends WebTestCase
{
    private static bool $schemaReady = false;

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        if (!self::$schemaReady) {
            $this->resetSchema();
            self::$schemaReady = true;
        } else {
            $this->purgeTables();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    protected function resetSchema(): void
    {
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    protected function purgeTables(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('PRAGMA foreign_keys = OFF');
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $meta) {
            $table = $meta->getTableName();
            // reserved word for User entity
            $quoted = $conn->quoteSingleIdentifier($table);
            $conn->executeStatement("DELETE FROM {$quoted}");
        }
        $conn->executeStatement('PRAGMA foreign_keys = ON');
        $this->em->clear();
    }

    protected function createUser(
        string $email = 'user@example.com',
        string $password = 'DevUserPass!12',
        array $roles = ['ROLE_USER'],
        string $username = 'testuser',
        bool $verified = true,
        bool $suspended = false,
    ): User {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username);
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setIsVerified($verified);
        $user->setSuspended($suspended);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function login(User $user): void
    {
        $this->client->loginUser($user);
    }
}
