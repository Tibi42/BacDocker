<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Repository des utilisateurs.
 *
 * Implémente PasswordUpgraderInterface pour la mise à niveau automatique
 * des hachages de mots de passe lors de la connexion.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Retourne tous les utilisateurs ayant ROLE_ADMIN ou ROLE_SUPER_ADMIN.
     * Utilisé pour envoyer les notifications aux admins.
     *
     * @return User[]
     */
    public function findAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles LIKE :admin OR u.roles LIKE :superadmin')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->setParameter('superadmin', '%ROLE_SUPER_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * QueryBuilder pour la liste admin (pagination KnpPaginator).
     */
    public function findAllFilteredQb(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $role = null, ?string $search = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC');

        if ($from !== null) {
            $qb->andWhere('u.createdAt >= :from')
                ->setParameter('from', $from);
        }
        if ($to !== null) {
            $qb->andWhere('u.createdAt <= :to')
                ->setParameter('to', $to->setTime(23, 59, 59));
        }
        if ($role === 'admin') {
            $qb->andWhere('u.roles LIKE :admin OR u.roles LIKE :superadmin')
                ->setParameter('admin', '%ROLE_ADMIN%')
                ->setParameter('superadmin', '%ROLE_SUPER_ADMIN%');
        } elseif ($role === 'user') {
            $qb->andWhere('u.roles NOT LIKE :admin AND u.roles NOT LIKE :superadmin')
                ->setParameter('admin', '%ROLE_ADMIN%')
                ->setParameter('superadmin', '%ROLE_SUPER_ADMIN%');
        }
        if ($search) {
            $qb->andWhere('u.email LIKE :search OR u.username LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
    }

    /**
     * Retourne les utilisateurs filtrés par période d'inscription, rôle et recherche texte.
     * Utilisé par l'export CSV (tous les résultats, sans pagination).
     *
     * @return User[]
     */
    public function findAllFiltered(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $role = null, ?string $search = null): array
    {
        return $this->findAllFilteredQb($from, $to, $role, $search)->getQuery()->getResult();
    }

    /**
     * Retourne le nombre total d'utilisateurs inscrits (utilisé dans le tableau de bord admin).
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Met à jour le mot de passe haché lors de la mise à niveau automatique
     * de l'algorithme de hachage (requis par PasswordUpgraderInterface).
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
