<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns an organization's primary colour into the CSS variables the Tailwind
 * primary-50/600/700 tokens read (space separated RGB triplets).
 */
final class BrandColor
{
    public static function cssVariables(?string $hex): string
    {
        $rgb = self::toRgb($hex) ?? self::toRgb((string) config('rentora.defaults.primary_color'));
        [$r, $g, $b] = $rgb ?? [26, 115, 232];

        $shades = [
            '50' => self::mix([$r, $g, $b], [255, 255, 255], 0.9),
            '600' => [$r, $g, $b],
            '700' => self::mix([$r, $g, $b], [0, 0, 0], 0.12),
        ];

        $lines = [];
        foreach ($shades as $shade => [$sr, $sg, $sb]) {
            $lines[] = "--color-primary-{$shade}: {$sr} {$sg} {$sb};";
        }

        return implode(' ', $lines);
    }

    /** @return array{int, int, int}|null */
    public static function toRgb(?string $hex): ?array
    {
        if ($hex === null || ! preg_match('/^#?([0-9a-f]{6})$/i', trim($hex), $m)) {
            return null;
        }

        return [
            (int) hexdec(substr($m[1], 0, 2)),
            (int) hexdec(substr($m[1], 2, 2)),
            (int) hexdec(substr($m[1], 4, 2)),
        ];
    }

    /**
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     * @return array{int, int, int}
     */
    private static function mix(array $from, array $to, float $amount): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        ];
    }
}
