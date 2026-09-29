<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstNonTestDatabase();
    }

    /**
     * RefreshDatabase vide la base : on refuse de tourner sur autre chose
     * qu'une base locale dédiée aux tests (le .env de dev pointe sur Railway).
     */
    private function guardAgainstNonTestDatabase(): void
    {
        $config = config('database.connections.'.config('database.default'));
        $host = $config['host'] ?? '';
        $name = $config['database'] ?? '';

        if (! in_array($host, ['127.0.0.1', 'localhost', 'db-test'], true) || ! str_ends_with($name, '_test')) {
            throw new RuntimeException(
                "Tests refusés : la connexion pointe sur [{$host}/{$name}]. "
                .'Une base locale dont le nom finit par _test est requise (voir docker-compose.test.yml).'
            );
        }
    }
}
