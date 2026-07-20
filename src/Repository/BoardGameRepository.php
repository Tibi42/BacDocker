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
     * Utilisé par KnpPaginator. Recherche optionnelle sur titre, catégorie, notes et état.
     */
    public function findAllOrderByTitleQb(?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.borrower', 'u')
            ->addSelect('u')
            ->andWhere('b.archived = false')
            ->orderBy('b.title', 'ASC');

        $term = $search !== null ? trim($search) : '';
        if ($term !== '') {
            $qb->andWhere(
                'LOWER(b.title) LIKE :search
                OR LOWER(COALESCE(b.category, \'\')) LIKE :search
                OR LOWER(COALESCE(b.notes, \'\')) LIKE :search
                OR LOWER(COALESCE(b.condition, \'\')) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($term) . '%');
        }

        return $qb;
    }

    /**
     * @return BoardGame[]
     */
    public function findAllOrderByTitle(?string $search = null): array
    {
        return $this->findAllOrderByTitleQb($search)->getQuery()->getResult();
    }

    /**
     * Suggestions d'autocomplétion (titre / catégorie), jeux non archivés.
     *
     * @return list<array{id: int, title: string, category: ?string}>
     */
    public function findSuggestions(string $term, int $limit = 8): array
    {
        $term = trim($term);
        if ($term === '' || mb_strlen($term) < 2) {
            return [];
        }

        $limit = max(1, min($limit, 20));

        $rows = $this->createQueryBuilder('b')
            ->select('b.id', 'b.title', 'b.category')
            ->andWhere('b.archived = false')
            ->andWhere(
                'LOWER(b.title) LIKE :search
                OR LOWER(COALESCE(b.category, \'\')) LIKE :search'
            )
            ->setParameter('search', '%' . mb_strtolower($term) . '%')
            ->orderBy('b.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'category' => $row['category'] !== null ? (string) $row['category'] : null,
        ], $rows);
    }

    /**
     * Jeux déjà empruntés par le membre (LoanLog), plus sa demande / son emprunt en cours.
     * Utilisé dans /mon-espace pour n'afficher que l'historique personnel.
     */
    public function findBorrowedByUserQb(User $user, ?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.borrower', 'u')
            ->addSelect('u')
            ->andWhere('b.archived = false')
            ->andWhere(
                'EXISTS (
                    SELECT 1 FROM App\Entity\LoanLog l
                    WHERE l.boardGame = b AND l.user = :user
                )
                OR (b.borrower = :user AND b.status IN (:activeStatuses))'
            )
            ->setParameter('user', $user)
            ->setParameter('activeStatuses', [BoardGame::STATUS_PENDING, BoardGame::STATUS_LOANED])
            ->orderBy('b.title', 'ASC');

        $term = $search !== null ? trim($search) : '';
        if ($term !== '') {
            $qb->andWhere(
                'LOWER(b.title) LIKE :search
                OR LOWER(COALESCE(b.category, \'\')) LIKE :search
                OR LOWER(COALESCE(b.notes, \'\')) LIKE :search
                OR LOWER(COALESCE(b.condition, \'\')) LIKE :search'
            )->setParameter('search', '%' . mb_strtolower($term) . '%');
        }

        return $qb;
    }

    /**
     * Jeux éligibles à une suppression en masse (disponibles, non archivés).
     *
     * @param list<int> $ids
     * @return list<BoardGame>
     */
    public function findForBulkDelete(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<BoardGame> $games */
        $games = $this->createQueryBuilder('b')
            ->andWhere('b.id IN (:ids)')
            ->andWhere('b.archived = false')
            ->andWhere('b.status = :status')
            ->setParameter('ids', $ids)
            ->setParameter('status', BoardGame::STATUS_AVAILABLE)
            ->getQuery()
            ->getResult();

        return $games;
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
            ->orderBy('b.returnDueAt', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
