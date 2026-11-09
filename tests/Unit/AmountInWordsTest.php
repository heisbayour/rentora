<?php

declare(strict_types=1);

use App\Support\AmountInWords;
use App\Support\Money;
use App\Support\NameMask;

it('spells numbers in British English', function (int $number, string $words) {
    expect(AmountInWords::number($number))->toBe($words);
})->with([
    [0, 'zero'],
    [7, 'seven'],
    [15, 'fifteen'],
    [42, 'forty-two'],
    [100, 'one hundred'],
    [105, 'one hundred and five'],
    [1005, 'one thousand and five'],
    [250000, 'two hundred and fifty thousand'],
    [1250000, 'one million, two hundred and fifty thousand'],
    [2000017, 'two million and seventeen'],
]);

it('spells naira and kobo amounts', function () {
    expect(AmountInWords::money(Money::of(125_000_050, 'NGN')))->toBe('One million, two hundred and fifty thousand naira and fifty kobo only')
        ->and(AmountInWords::money(Money::of(100, 'NGN')))->toBe('One naira only');
});

it('masks names for the public receipt check', function () {
    expect(NameMask::mask('Adaeze Okafor'))->toBe('Ad*** Ok***')
        ->and(NameMask::mask('  Tunde  '))->toBe('Tu***');
});
