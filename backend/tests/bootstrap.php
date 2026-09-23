<?php

/**
 * compose.yml sets DB_CONNECTION/DB_HOST/etc. as real container environment
 * variables for php-fpm, populating both getenv() and $_ENV/$_SERVER before
 * PHP even starts. phpunit.xml's <php><env force="true"> only reliably wins
 * on getenv(); Laravel's env() helper can still read the pre-existing
 * $_ENV/$_SERVER values, so config('database.default') would silently stay
 * 'pgsql' and RefreshDatabase's migrate:fresh would wipe the real dev
 * database. Force all three sources here, before anything else loads, so
 * there's no ambiguity. Confirmed the hard way: do not remove this file or
 * revert phpunit.xml's bootstrap attribute back to vendor/autoload.php.
 */
$testEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
];

foreach ($testEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
