<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['user'];
    }

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Comptes de développement uniquement — ne jamais y mettre d'identifiants réels.
        $users = [
            [
                'email'    => 'superadmin@example.com',
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
