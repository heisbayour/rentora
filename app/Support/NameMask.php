<?php

declare(strict_types=1);

namespace App\Support;

/** "Adaeze Okafor" becomes "Ad*** Ok***" on the public receipt check (spec 8.6). */
final class NameMask
{
    public static function mask(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return implode(' ', array_map(fn (string $part) => mb_substr($part, 0, 2).'***', array_filter($parts)));
    }
}
