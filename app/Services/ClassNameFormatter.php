<?php

namespace App\Services;

class ClassNameFormatter
{
    public static function format(string $firstName, string $lastName): string
    {
        $firstParts = preg_split('/\s+/u', mb_strtolower(trim($firstName)));
        $formattedFirst = implode('-', array_map(
            fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)).mb_substr($part, 1),
            $firstParts,
        ));

        $formattedLast = mb_strtoupper(trim($lastName));

        return trim("{$formattedFirst} {$formattedLast}");
    }
}
