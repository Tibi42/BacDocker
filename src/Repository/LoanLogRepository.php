<?php

namespace App\Repository;

use App\Entity\BoardGame;
use App\Entity\LoanLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanLog>
 */
class LoanLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanLog::class);
    }

    /**
     * Indique si cet utilisateur a déjà emprunté ce jeu au moins une fois.
     * Utilisé pour l'éligibilité à la notation (Review).
     */
    public function hasBorrowed(BoardGame $boardGame, User $user): bool
    {
        return $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.boardGame = :game')
            ->andWhere('l.user = :user')
            ->setParameter('game', $boardGame)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
