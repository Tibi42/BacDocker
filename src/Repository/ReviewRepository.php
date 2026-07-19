<?php

namespace App\Repository;

use App\Entity\BoardGame;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Moyenne des notes pour ce jeu, ou null s'il n'a aucune note.
     */
    public function averageFor(BoardGame $boardGame): ?float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->andWhere('r.boardGame = :game')
            ->setParameter('game', $boardGame)
            ->getQuery()
            ->getSingleScalarResult();

        return $result === null ? null : (float) $result;
    }

    /**
     * Note de cet utilisateur pour ce jeu, s'il en a déjà mis une.
     */
    public function findOneForUserAndGame(BoardGame $boardGame, User $user): ?Review
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.boardGame = :game')
            ->andWhere('r.user = :user')
            ->setParameter('game', $boardGame)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
