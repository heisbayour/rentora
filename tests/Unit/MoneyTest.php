<?php

declare(strict_types=1);

use App\Support\Money;

it('formats naira with separators and two decimals', function (int $minor, string $expected) {
    expect(Money::of($minor, 'NGN')->format())->toBe($expected);
})->with([
    [125_000_000, '₦1,250,000.00'],
    [5, '₦0.05'],
    [0, '₦0.00'],
    [-50_000, '-₦500.00'],
    [99, '₦0.99'],
]);

it('parses major amounts without floats', function (string $input, int $minor) {
    expect(Money::parse($input, 'NGN')->minor)->toBe($minor);
})->with([
    ['1,250,000.50', 125_000_050],
    ['1250000', 125_000_000],
    ['0.1', 10],
    ['.5', 50],
    ['-20.25', -2025],
    ['19.99', 1999],
]);

it('rejects text that is not an amount', function (string $input) {
    Money::parse($input, 'NGN');
})->throws(InvalidArgumentException::class)->with(['', 'abc', '1.234', '1.2.3', '-']);

it('adds, subtracts and compares in the same currency', function () {
    $a = Money::of(10_000, 'NGN');
    $b = Money::of(2_550, 'NGN');

    expect($a->add($b)->minor)->toBe(12_550)
        ->and($a->subtract($b)->minor)->toBe(7_450)
        ->and($b->subtract($a)->isNegative())->toBeTrue()
        ->and($a->greaterThan($b))->toBeTrue()
        ->and($a->min($b)->equals($b))->toBeTrue()
        ->and(Money::zero('NGN')->isZero())->toBeTrue();
});

it('refuses to mix currencies', function () {
    Money::of(100, 'NGN')->add(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class);

it('takes percentages in basis points, rounding half up', function () {
    expect(Money::of(100_000, 'NGN')->percentage(500)->minor)->toBe(5_000)
        ->and(Money::of(333, 'NGN')->percentage(5000)->minor)->toBe(167)
        ->and(Money::of(1, 'NGN')->percentage(4999)->minor)->toBe(0);
});

it('uses the right symbol and a plain decimal string for forms', function () {
    expect(Money::of(150, 'USD')->format())->toBe('$1.50')
        ->and(Money::of(150, 'XYZ')->format())->toBe('XYZ 1.50')
        ->and(Money::of(125_000_050, 'NGN')->toDecimalString())->toBe('1250000.50');
});
