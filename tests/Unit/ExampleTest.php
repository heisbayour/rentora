<?php

declare(strict_types=1);

it('reads the Rentora version from config', function () {
    expect(config('rentora.version'))->toBe('1.0.0');
});
