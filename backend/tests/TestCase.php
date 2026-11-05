<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard safety net: refuse to run any test against a connection other
     * than the disposable in-memory sqlite one. Tests use RefreshDatabase,
     * which drops and recreates every table; see tests/bootstrap.php for
     * why this isn't automatically guaranteed by env configuration alone.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            throw new RuntimeException(
                "Refusing to run tests: database.default is '{$connection}', not 'sqlite'. ".
                'This would run RefreshDatabase against a real database. See tests/bootstrap.php.'
            );
        }
    }
}
