<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Installation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests run as an installed site; installer tests remove the lock themselves.
        // The lock lives in storage/framework/testing (phpunit.xml RENTORA_INSTALL_LOCK).
        Installation::markInstalled();
    }
}
