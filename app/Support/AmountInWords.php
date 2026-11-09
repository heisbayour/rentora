<?php

declare(strict_types=1);

namespace App\Support;

/**
 * "One million, two hundred and fifty thousand naira and fifty kobo" for
 * receipts (spec 8.6). Self-contained: shared hosts may lack the intl extension.
 */
final class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    private const SCALES = ['', 'thousand', 'million', 'billion', 'trillion'];

    private const UNITS = [
        'NGN' => ['naira', 'naira', 'kobo'],
        'GHS' => ['cedi', 'cedis', 'pesewas'],
        'KES' => ['shilling', 'shillings', 'cents'],
        'USD' => ['dollar', 'dollars', 'cents'],
    ];

    public static function money(Money $money): string
    {
        [$singular, $plural, $minorName] = self::UNITS[$money->currency] ?? ['unit', 'units', 'cents'];
        $absolute = abs($money->minor);
        $major = intdiv($absolute, 100);
        $minor = $absolute % 100;

        $text = self::number($major).' '.($major === 1 ? $singular : $plural);
        if ($minor > 0) {
            $text .= ' and '.self::number($minor).' '.$minorName;
        }

        return ucfirst(($money->minor < 0 ? 'minus ' : '').$text).' only';
    }

    public static function number(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        $groups = [];
        $scale = 0;
        while ($number > 0) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $groups[] = self::belowThousand($chunk).(self::SCALES[$scale] !== '' ? ' '.self::SCALES[$scale] : '');
            }
            $number = intdiv($number, 1000);
            $scale++;
        }
        $groups = array_reverse($groups);

        // British style: "one thousand and five" when the last group is below 100.
        $last = count($groups) - 1;
        if ($last > 0 && ! str_contains($groups[$last], 'hundred')) {
            return implode(', ', array_slice($groups, 0, $last)).' and '.$groups[$last];
        }

        return implode(', ', $groups);
    }

    private static function belowThousand(int $number): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;
        $words = $hundreds > 0 ? self::ONES[$hundreds].' hundred' : '';
        if ($rest > 0) {
            $words .= ($words !== '' ? ' and ' : '').self::belowHundred($rest);
        }

        return $words;
    }

    private static function belowHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        return self::TENS[intdiv($number, 10)].($number % 10 > 0 ? '-'.self::ONES[$number % 10] : '');
    }
}
