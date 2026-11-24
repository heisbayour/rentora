<?php

declare(strict_types=1);

use App\Support\PhoneNormalizer;

it('normalizes phone numbers to E.164 with Nigeria as the default country', function (string $input, ?string $expected) {
    expect(PhoneNormalizer::toE164($input, '234'))->toBe($expected);
})->with([
    ['08031234567', '+2348031234567'],
    ['0803 123 4567', '+2348031234567'],
    ['0803-123-4567', '+2348031234567'],
    ['2348031234567', '+2348031234567'],
    ['+234 803 123 4567', '+2348031234567'],
    ['8031234567', '+2348031234567'],
    ['00447911123456', '+447911123456'],
    ['+447911123456', '+447911123456'],
    ['0803123456', null],
    ['+23480312345678', null],
    ['12', null],
    ['', null],
    ['abc', null],
]);

it('builds wa.me links with an encoded message', function () {
    expect(PhoneNormalizer::whatsAppLink('08031234567', 'Hi & bye'))->toBe('https://wa.me/2348031234567?text=Hi%20%26%20bye')
        ->and(PhoneNormalizer::whatsAppLink('bad'))->toBeNull();
});
