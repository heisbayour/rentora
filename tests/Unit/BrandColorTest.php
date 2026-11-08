<?php

declare(strict_types=1);

use App\Support\BrandColor;

it('converts the default primary colour to RGB triplets', function () {
    $css = BrandColor::cssVariables('#1A73E8');

    expect($css)
        ->toContain('--color-primary-600: 26 115 232;')
        ->toContain('--color-primary-700:')
        ->toContain('--color-primary-50:');
});

it('derives a darker 700 and a lighter 50 shade', function () {
    $css = BrandColor::cssVariables('#000000');

    expect($css)->toContain('--color-primary-700: 0 0 0;')
        ->toContain('--color-primary-50: 230 230 230;');
});

it('falls back to the configured default for invalid input', function (?string $input) {
    expect(BrandColor::cssVariables($input))->toContain('--color-primary-600: 26 115 232;');
})->with([null, '', 'red', '#12345', 'javascript:alert(1)']);
