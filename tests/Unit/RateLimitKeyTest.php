<?php

namespace App\Tests\Unit;

use App\Util\RateLimitKey;
use PHPUnit\Framework\TestCase;

class RateLimitKeyTest extends TestCase
{
    public function testIsDeterministicSha256(): void
    {
        $key = RateLimitKey::forIpAndIdentifier('127.0.0.1', 'user@example.com');

        $this->assertSame(64, strlen($key));
        $this->assertSame(
            hash('sha256', '127.0.0.1|user@example.com'),
            $key
        );
    }

    public function testNormalizesIdentifierCaseAndWhitespace(): void
    {
        $a = RateLimitKey::forIpAndIdentifier('1.2.3.4', '  User@Example.COM ');
        $b = RateLimitKey::forIpAndIdentifier('1.2.3.4', 'user@example.com');

        $this->assertSame($a, $b);
    }

    public function testDifferentIpsProduceDifferentKeys(): void
    {
        $a = RateLimitKey::forIpAndIdentifier('1.1.1.1', 'same@example.com');
        $b = RateLimitKey::forIpAndIdentifier('2.2.2.2', 'same@example.com');

        $this->assertNotSame($a, $b);
    }
}
