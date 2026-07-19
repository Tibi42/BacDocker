<?php

namespace App\Tests\Unit;

use App\Entity\BoardGame;
use App\Entity\User;
use App\Repository\LoanLogRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LoanLogRepositoryTest extends TestCase
{
    private function makeRepo(ManagerRegistry $registry, QueryBuilder $qb): LoanLogRepository
    {
        return new class($registry, $qb) extends LoanLogRepository {
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

    public function testHasBorrowedReturnsTrueWhenScalarIsPositive(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $boardGame = $this->createMock(BoardGame::class);
        $user = $this->createMock(User::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('1');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('COUNT(l.id)')->willReturnSelf();

        $expectedAndWheres = [
            'l.boardGame = :game',
            'l.user = :user',
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

        $this->assertTrue($repo->hasBorrowed($boardGame, $user));
    }

    public function testHasBorrowedReturnsFalseWhenScalarIsZero(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $boardGame = $this->createMock(BoardGame::class);
        $user = $this->createMock(User::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('0');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('COUNT(l.id)')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('andWhere')->willReturnSelf();
        $qb->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepo($registry, $qb);

        $this->assertFalse($repo->hasBorrowed($boardGame, $user));
    }
}
