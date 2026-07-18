<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Inscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inscription>
 */
class InscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inscription::class);
    }

    public function countForActivity(int $activityId): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.activity = :activityId')
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasAlreadyRegistered(int $activityId, string $email): bool
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.activity = :activityId')
            ->andWhere('i.participantEmail = :email')
            ->setParameter('activityId', $activityId)
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Inscription[] Liste triée des inscriptions les plus récentes, avec la relation Activity.
     */
    /**
     * @return Inscription[]
     */
    public function findByActivity(Activity $activity): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.activity = :activity')
            ->setParameter('activity', $activity)
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, int> [activityId => count]
     */
    public function countByActivity(): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.activity) AS activityId, COUNT(i.id) AS cnt')
            ->groupBy('i.activity')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['activityId']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * @return Inscription[] Liste triée des inscriptions les plus récentes, avec la relation Activity.
     */
    public function findLatestWithActivity(int $limit = 1): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.activity', 'a')
            ->addSelect('a')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
