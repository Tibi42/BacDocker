<?php

namespace App\Repository;

use App\Entity\BoardGame;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository des jeux de la ludothèque.
 *
 * Fournit la liste paginée pour l'admin (triée par titre) et la vérification
 * qu'un membre n'a pas déjà un emprunt/une demande en cours.
 *
 * @extends ServiceEntityRepository<BoardGame>
 */
class BoardGameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoardGame::class);
    }

    /**
     * Retourne un QueryBuilder pour les jeux triés par titre (asc).
     * Utilisé par KnpPaginator pour paginer les résultats de l'admin.
     */
    public function findAllOrderByTitleQb(): QueryBuilder
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.borrower', 'u')
            ->addSelect('u')
            ->andWhere('b.archived = false')
            ->orderBy('b.title', 'ASC');
    }

    /**
     * @return BoardGame[]
     */
    public function findAllOrderByTitle(): array
    {
        return $this->findAllOrderByTitleQb()->getQuery()->getResult();
    }

    /**
     * Retourne le jeu actuellement pending/loaned pour cet utilisateur, s'il en a un.
     * Utilisé pour interdire à un membre d'avoir plus d'un jeu actif en même temps.
     */
    public function findActiveForUser(User $user): ?BoardGame
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('b.borrower = :user')
            ->setParameter('statuses', [BoardGame::STATUS_PENDING, BoardGame::STATUS_LOANED])
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
