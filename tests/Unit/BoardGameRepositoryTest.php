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

        $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

        $result = $repo->findAllOrderByTitleQb();

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
                $this->assertSame($expectedAndWheres[$andWhereCallIndex], $where);
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
}
