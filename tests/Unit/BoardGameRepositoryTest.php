<?php

namespace App\Tests\Unit;

use App\Entity\BoardGame;
use App\Entity\User;
use App\Repository\BoardGameRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class BoardGameRepositoryTest extends TestCase
{
    private function makeRepoWithQueryBuilder(ManagerRegistry $registry, QueryBuilder $qb): BoardGameRepository
    {
        return new class($registry, $qb) extends BoardGameRepository {
            public function __construct(
                private readonly ManagerRegistry $registryMock,
                private readonly QueryBuilder $qbMock,
            ) {
                parent::__construct($this->registryMock);
            }

            public function createQueryBuilder(string $alias, string|null $indexBy = null): QueryBuilder
            {
                return $this->qbMock;
            }
        };
    }

    public function testFindAllOrderByTitleQbBuildsQuery(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('leftJoin')->with('b.borrower', 'u')->willReturnSelf();
        $qb->expects($this->once())->method('addSelect')->with('u')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('b.archived = false')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('b.title', 'ASC')->willReturnSelf();
        $qb->expects($this->never())->method('setParameter');

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findAllOrderByTitleQb();

        $this->assertSame($qb, $result);
    }

    public function testFindAllOrderByTitleQbAppliesSearchFilter(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('leftJoin')->with('b.borrower', 'u')->willReturnSelf();
        $qb->expects($this->once())->method('addSelect')->with('u')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('b.title', 'ASC')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('search', '%catan%')->willReturnSelf();

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findAllOrderByTitleQb('  Catan  ');

        $this->assertSame($qb, $result);
    }

    public function testFindAllOrderByTitleReturnsResults(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $expected = ['board-game-1', 'board-game-2'];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getResult')->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('leftJoin')->with('b.borrower', 'u')->willReturnSelf();
        $qb->expects($this->once())->method('addSelect')->with('u')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('b.archived = false')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('b.title', 'ASC')->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findAllOrderByTitle();

        $this->assertSame($expected, $result);
    }

    public function testFindSuggestionsReturnsEmptyForShortTerm(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('select');

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $this->assertSame([], $repo->findSuggestions('a'));
        $this->assertSame([], $repo->findSuggestions('  '));
    }

    public function testFindSuggestionsBuildsQuery(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $expected = [
            ['id' => 1, 'title' => 'Catan', 'category' => 'Stratégie'],
        ];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getArrayResult')->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('b.id', 'b.title', 'b.category')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('andWhere')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('search', '%cat%')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('b.title', 'ASC')->willReturnSelf();
        $qb->expects($this->once())->method('setMaxResults')->with(8)->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findSuggestions('Cat');

        $this->assertSame($expected, $result);
    }

    public function testFindForBulkDeleteReturnsEmptyForEmptyIds(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $this->assertSame([], $repo->findForBulkDelete([]));
    }

    public function testFindForBulkDeleteFiltersAvailableNonArchived(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->exactly(3))->method('andWhere')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $this->assertSame([], $repo->findForBulkDelete([1, 2, 3]));
    }

    public function testFindActiveForUserBuildsQueryAndReturnsResult(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $user = new User();
        $expected = $this->createMock(BoardGame::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getOneOrNullResult')->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);

        $expectedAndWheres = [
            'b.status IN (:statuses)',
            'b.borrower = :user',
        ];
        $andWhereCallIndex = 0;
        $qb->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (string $where) use (&$andWhereCallIndex, $expectedAndWheres, $qb) {
                $normalize = static fn (string $s): string => str_replace("\r\n", "\n", $s);
                $this->assertSame($normalize($expectedAndWheres[$andWhereCallIndex]), $normalize($where));
                $andWhereCallIndex++;

                return $qb;
            });

        $expectedParameters = [
            ['statuses', [BoardGame::STATUS_PENDING, BoardGame::STATUS_LOANED]],
            ['user', $user],
        ];
        $setParameterCallIndex = 0;
        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$setParameterCallIndex, $expectedParameters, $qb) {
                $this->assertSame($expectedParameters[$setParameterCallIndex][0], $key);
                $this->assertSame($expectedParameters[$setParameterCallIndex][1], $value);
                $setParameterCallIndex++;

                return $qb;
            });

        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findActiveForUser($user);

        $this->assertSame($expected, $result);
    }

    public function testFindBorrowedByUserQbBuildsQuery(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $user = new User();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('leftJoin')->with('b.borrower', 'u')->willReturnSelf();
        $qb->expects($this->once())->method('addSelect')->with('u')->willReturnSelf();

        $expectedAndWheres = [
            'b.archived = false',
            'EXISTS (
                    SELECT 1 FROM App\Entity\LoanLog l
                    WHERE l.boardGame = b AND l.user = :user
                )
                OR (b.borrower = :user AND b.status IN (:activeStatuses))',
        ];
        $andWhereCallIndex = 0;
        $qb->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (string $where) use (&$andWhereCallIndex, $expectedAndWheres, $qb) {
                $normalize = static fn (string $s): string => str_replace("\r\n", "\n", $s);
                $this->assertSame($normalize($expectedAndWheres[$andWhereCallIndex]), $normalize($where));
                $andWhereCallIndex++;

                return $qb;
            });

        $expectedParameters = [
            ['user', $user],
            ['activeStatuses', [BoardGame::STATUS_PENDING, BoardGame::STATUS_LOANED]],
        ];
        $setParameterCallIndex = 0;
        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$setParameterCallIndex, $expectedParameters, $qb) {
                $this->assertSame($expectedParameters[$setParameterCallIndex][0], $key);
                $this->assertSame($expectedParameters[$setParameterCallIndex][1], $value);
                $setParameterCallIndex++;

                return $qb;
            });

        $qb->expects($this->once())->method('orderBy')->with('b.title', 'ASC')->willReturnSelf();

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findBorrowedByUserQb($user);

        $this->assertSame($qb, $result);
    }

    public function testFindBorrowedByUserQbAppliesSearchFilter(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $user = new User();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('leftJoin')->with('b.borrower', 'u')->willReturnSelf();
        $qb->expects($this->once())->method('addSelect')->with('u')->willReturnSelf();
        $qb->expects($this->exactly(3))->method('andWhere')->willReturnSelf();

        $expectedParameters = [
            ['user', $user],
            ['activeStatuses', [BoardGame::STATUS_PENDING, BoardGame::STATUS_LOANED]],
            ['search', '%azul%'],
        ];
        $setParameterCallIndex = 0;
        $qb->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnCallback(function (string $key, mixed $value) use (&$setParameterCallIndex, $expectedParameters, $qb) {
                $this->assertSame($expectedParameters[$setParameterCallIndex][0], $key);
                $this->assertSame($expectedParameters[$setParameterCallIndex][1], $value);
                $setParameterCallIndex++;

                return $qb;
            });

        $qb->expects($this->once())->method('orderBy')->with('b.title', 'ASC')->willReturnSelf();

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findBorrowedByUserQb($user, '  Azul  ');

        $this->assertSame($qb, $result);
    }
}
