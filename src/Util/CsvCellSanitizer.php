<?php

namespace App\Util;

/**
 * Neutralise les injections de formules Excel/CSV (=, +, @…) à l'export.
 */
final class CsvCellSanitizer
{
    public static function sanitize(string $value): string
    {
        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "\t" . $value;
        }

        return $value;
    }
}
