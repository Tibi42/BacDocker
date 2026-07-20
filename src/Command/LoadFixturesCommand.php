<?php
/**
 * Standalone fixtures loader for development only.
 * Usage: php bin/console load-fixtures
 */

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'load-fixtures',
    description: 'Load fixture users (dev/test only — refused in production)',
)]
class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->environment === 'prod') {
            $output->writeln('<error>Refusé en production.</error>');

            return Command::FAILURE;
        }

        $output->writeln('Loading fixtures...');

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

        foreach ($users as $data) {
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existing) {
                $output->writeln('  · Skip (existe déjà) : ' . $data['email']);
                continue;
            }

            $user = new User();
            $user->setEmail($data['email']);
            $user->setUsername($data['username']);
            $user->setRoles($data['roles']);
            $user->setPassword($this->hasher->hashPassword($user, $data['password']));
            $user->setIsVerified(true);
            $this->em->persist($user);
            $output->writeln('  ✓ User: ' . $data['email']);
        }

        $this->em->flush();
        $output->writeln('<info>Fixtures loaded successfully!</info>');
        $output->writeln('<comment>Changez ces mots de passe si l\'environnement n\'est pas purement local.</comment>');

        return Command::SUCCESS;
    }
}
