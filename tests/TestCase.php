<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUpTraits()
    {
        $this->ensureTestingDatabaseIsSafe();

        return parent::setUpTraits();
    }

    private function ensureTestingDatabaseIsSafe(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Tests can only run in the testing environment.');
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");
        $database = config("database.connections.{$connection}.database");

        if ($driver === 'sqlite' && $database === ':memory:') {
            return;
        }

        throw new RuntimeException(
            "Refusing to refresh the '{$connection}' database. ".
            'Feature tests must use the sqlite :memory: database to protect local data.'
        );
    }
}
