<?php

namespace App\Tests\Unit;

use App\Entity\BoardGame;
use PHPUnit\Framework\TestCase;

class BoardGameTest extends TestCase
{
    public function testDefaultStatusIsAvailable(): void
    {
        $boardGame = new BoardGame();

        $this->assertSame(BoardGame::STATUS_AVAILABLE, $boardGame->getStatus());
    }

    public function testSetStatusPending(): void
    {
        $boardGame = new BoardGame();
        $boardGame->setStatus(BoardGame::STATUS_PENDING);

        $this->assertSame(BoardGame::STATUS_PENDING, $boardGame->getStatus());
    }

    public function testSetStatusLoaned(): void
    {
        $boardGame = new BoardGame();
        $boardGame->setStatus(BoardGame::STATUS_LOANED);

        $this->assertSame(BoardGame::STATUS_LOANED, $boardGame->getStatus());
    }

    public function testSetStatusRejectsInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $boardGame = new BoardGame();
        $boardGame->setStatus('invalid-status');
    }

    public function testSetCreatedAtValueSetsCreatedAtAndUpdatedAt(): void
    {
        $boardGame = new BoardGame();

        $this->assertNull($boardGame->getCreatedAt());
        $this->assertNull($boardGame->getUpdatedAt());

        $boardGame->setCreatedAtValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $boardGame->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $boardGame->getUpdatedAt());
        $this->assertGreaterThanOrEqual(
            $boardGame->getCreatedAt()->getTimestamp(),
            $boardGame->getUpdatedAt()->getTimestamp()
        );
    }

    public function testSetUpdatedAtValueSetsUpdatedAt(): void
    {
        $boardGame = new BoardGame();

        $this->assertNull($boardGame->getUpdatedAt());

        $boardGame->setUpdatedAtValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $boardGame->getUpdatedAt());
    }

    public function testSetRequestedAtConvertsToDateTimeImmutable(): void
    {
        $boardGame = new BoardGame();

        $requestedAt = new \DateTime('2026-03-20 10:00:00');
        $boardGame->setRequestedAt($requestedAt);

        $this->assertInstanceOf(\DateTimeImmutable::class, $boardGame->getRequestedAt());
        $this->assertSame($requestedAt->getTimestamp(), $boardGame->getRequestedAt()->getTimestamp());
    }

    public function testSetLoanedAtAcceptsNull(): void
    {
        $boardGame = new BoardGame();
        $boardGame->setLoanedAt(new \DateTimeImmutable('2026-03-20 10:00:00'));
        $boardGame->setLoanedAt(null);

        $this->assertNull($boardGame->getLoanedAt());
    }

    public function testSetReturnDueAtConvertsToDateTimeImmutable(): void
    {
        $boardGame = new BoardGame();

        $returnDueAt = new \DateTime('2026-04-01 00:00:00');
        $boardGame->setReturnDueAt($returnDueAt);

        $this->assertInstanceOf(\DateTimeImmutable::class, $boardGame->getReturnDueAt());
        $this->assertSame($returnDueAt->getTimestamp(), $boardGame->getReturnDueAt()->getTimestamp());
    }

    public function testArchivedDefaultsFalse(): void
    {
        $boardGame = new BoardGame();

        $this->assertFalse($boardGame->isArchived());
    }

    public function testSetArchivedRoundTrip(): void
    {
        $boardGame = new BoardGame();
        $boardGame->setArchived(true);

        $this->assertTrue($boardGame->isArchived());
    }

    public function testImageDefaultsNull(): void
    {
        $boardGame = new BoardGame();

        $this->assertNull($boardGame->getImage());
    }

    public function testSetImageRoundTrip(): void
    {
        $boardGame = new BoardGame();
        $boardGame->setImage('game-abc123.jpg');

        $this->assertSame('game-abc123.jpg', $boardGame->getImage());

        $boardGame->setImage(null);

        $this->assertNull($boardGame->getImage());
    }
}
