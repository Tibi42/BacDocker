<?php

namespace App\Tests\Unit;

use App\Entity\BoardGame;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ReviewRepositoryTest extends TestCase
{
    private function makeRepo(ManagerRegistry $registry, QueryBuilder $qb): ReviewRepository
    {
        return new class($registry, $qb) extends ReviewRepository {
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

    public function testAverageForReturnsFloatWhenReviewsExist(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $boardGame = $this->createMock(BoardGame::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('3.5');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('AVG(r.rating)')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('r.boardGame = :game')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('game', $boardGame)->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepo($registry, $qb);

        $this->assertSame(3.5, $repo->averageFor($boardGame));
    }

    public function testAverageForReturnsNullWhenNoReviews(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $boardGame = $this->createMock(BoardGame::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn(null);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('AVG(r.rating)')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('r.boardGame = :game')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('game', $boardGame)->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepo($registry, $qb);

        $this->assertNull($repo->averageFor($boardGame));
    }

    public function testFindOneForUserAndGameBuildsQueryAndReturnsResult(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $boardGame = $this->createMock(BoardGame::class);
        $user = $this->createMock(User::class);
        $expected = $this->createMock(Review::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getOneOrNullResult')->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);

        $expectedAndWheres = [
            'r.boardGame = :game',
            'r.user = :user',
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
            ['game', $boardGame],
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

        $repo = $this->makeRepo($registry, $qb);

        $result = $repo->findOneForUserAndGame($boardGame, $user);

        $this->assertSame($expected, $result);
    }
}
