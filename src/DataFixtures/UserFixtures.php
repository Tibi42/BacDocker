<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['user'];
    }

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {}

    public function load(ObjectManager $manager): void
    {
        if ($this->environment === 'prod') {
            throw new \RuntimeException('Les fixtures utilisateurs sont interdites en production.');
        }

        // Comptes de développement uniquement — emails @example.com, pas d'identifiants réels.
        $users = [
            [
                'email'    => 'boiteachimere@guillaumepecquet.ovh',
                'username' => 'superadmin',
                'roles'    => ['ROLE_SUPER_ADMIN'],
                'password' => 'DevSuperAdmin!12',
            ],
            [
                'email'    => 'admin@example.com',
                'username' => 'admin',
                'roles'    => ['ROLE_ADMIN'],
                'password' => 'DevAdminPass!12',
            ],
            [
                'email'    => 'user@example.com',
                'username' => 'user',
                'roles'    => ['ROLE_USER'],
                'password' => 'DevUserPass!12',
            ],
        ];

        // Utilisateurs supplémentaires pour tester la pagination admin (username unique).
        foreach (range('a', 'p') as $letter) {
            $users[] = [
                'email'    => sprintf('user%s@example.com', $letter),
                'username' => 'user' . $letter,
                'roles'    => ['ROLE_USER'],
                'password' => 'DevUserPass!12',
            ];
        }

        foreach ($users as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setUsername($data['username']);
            $user->setRoles($data['roles']);
            $user->setPassword($this->hasher->hashPassword($user, $data['password']));
            $user->setIsVerified(true);
            $manager->persist($user);
        }

        $manager->flush();
    }
}
