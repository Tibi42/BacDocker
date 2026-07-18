<?php

namespace App\Util;

/**
 * Clés de rate limiting combinant IP et identifiant (email, user id…).
 */
final class RateLimitKey
{
    public static function forIpAndIdentifier(string $ip, string $identifier): string
    {
        return hash('sha256', $ip . '|' . mb_strtolower(trim($identifier)));
    }
}
