<?php

namespace App\Tests\Unit;

use App\Entity\Review;
use PHPUnit\Framework\TestCase;

class ReviewTest extends TestCase
{
    public function testSetRatingAcceptsOne(): void
    {
        $review = new Review();
        $review->setRating(1);

        $this->assertSame(1, $review->getRating());
    }

    public function testSetRatingAcceptsFive(): void
    {
        $review = new Review();
        $review->setRating(5);

        $this->assertSame(5, $review->getRating());
    }

    public function testSetRatingRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $review = new Review();
        $review->setRating(0);
    }

    public function testSetRatingRejectsSix(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $review = new Review();
        $review->setRating(6);
    }

    public function testSetCreatedAtValueSetsCreatedAtAndUpdatedAt(): void
    {
        $review = new Review();

        $this->assertNull($review->getCreatedAt());
        $this->assertNull($review->getUpdatedAt());

        $review->setCreatedAtValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $review->getUpdatedAt());
        $this->assertGreaterThanOrEqual(
            $review->getCreatedAt()->getTimestamp(),
            $review->getUpdatedAt()->getTimestamp()
        );
    }

    public function testSetUpdatedAtValueSetsUpdatedAt(): void
    {
        $review = new Review();

        $this->assertNull($review->getUpdatedAt());

        $review->setUpdatedAtValue();

        $this->assertInstanceOf(\DateTimeImmutable::class, $review->getUpdatedAt());
    }
}
