<?php

namespace App\Tests\Unit;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class NewsletterSubscriberRepositoryTest extends TestCase
{
    private function makeRepo(ManagerRegistry $registry, QueryBuilder $qb): NewsletterSubscriberRepository
    {
        return new class($registry, $qb) extends NewsletterSubscriberRepository {
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

            public function findOneBy(array $criteria, array|null $orderBy = null): ?object
            {
                if (isset($criteria['email'])) {
                    $subscriber = new NewsletterSubscriber();
                    $subscriber->setEmail($criteria['email']);

                    return $subscriber;
                }

                if (isset($criteria['token'])) {
                    $subscriber = new NewsletterSubscriber();
                    $subscriber->setEmail('token@example.com');
                    $subscriber->regenerateToken();

                    return $subscriber;
                }

                return null;
            }
        };
    }

    public function testFindByEmailDelegatesToFindOneBy(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $qb = $this->createMock(QueryBuilder::class);
        $repo = $this->makeRepo($registry, $qb);

        $result = $repo->findByEmail('alice@example.com');

        $this->assertInstanceOf(NewsletterSubscriber::class, $result);
        $this->assertSame('alice@example.com', $result->getEmail());
    }

    public function testFindByTokenDelegatesToFindOneBy(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $qb = $this->createMock(QueryBuilder::class);
        $repo = $this->makeRepo($registry, $qb);

        $result = $repo->findByToken('abc123');

        $this->assertInstanceOf(NewsletterSubscriber::class, $result);
        $this->assertSame('token@example.com', $result->getEmail());
    }

    public function testFindConfirmedBuildsQuery(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $expected = [new NewsletterSubscriber()];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getResult')->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('n.status = :status')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('status', NewsletterSubscriber::STATUS_CONFIRMED)
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('orderBy')
            ->with('n.confirmedAt', 'DESC')
            ->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepo($registry, $qb);

        $this->assertSame($expected, $repo->findConfirmed());
    }

    public function testFindAllOrderedByDateBuildsQuery(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $expected = [];

        $query = $this->createMock(Query::class);
        $query->expects($this->once())->method('getResult')->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('orderBy')
            ->with('n.createdAt', 'DESC')
            ->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepo($registry, $qb);

        $this->assertSame($expected, $repo->findAllOrderedByDate());
    }

    public function testCountByStatusReturnsInteger(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getSingleScalarResult')
            ->willReturn('7');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('COUNT(n.id)')->willReturnSelf();
        $qb->expects($this->once())->method('andWhere')->with('n.status = :status')->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('status', NewsletterSubscriber::STATUS_CONFIRMED)
            ->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $repo = $this->makeRepo($registry, $qb);

        $this->assertSame(7, $repo->countByStatus(NewsletterSubscriber::STATUS_CONFIRMED));
    }
}
