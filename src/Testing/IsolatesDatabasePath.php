<?php

declare(strict_types=1);

namespace CoreX\Testing;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Override;
use RuntimeException;

/** Keeps published migrations out of the shared Testbench skeleton. */
trait IsolatesDatabasePath
{
    /** @var list<string> */
    private array $isolatedDatabasePaths = [];

    /** @param Application $app */
    protected function resolveApplicationCore($app): void
    {
        $database = getenv('PGSQL_DATABASE') ?: 'corex_test';

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException('Unsafe test database: PGSQL_DATABASE must end in _test.');
        }

        parent::resolveApplicationCore($app);

        $path = sys_get_temp_dir().'/corex-test-database-'.bin2hex(random_bytes(16));
        (new Filesystem)->makeDirectory($path.'/migrations', 0700, true);
        $this->isolatedDatabasePaths[] = $path;
        $app->useDatabasePath($path);
        (new Filesystem)->makeDirectory($path.'/bootstrap/cache', 0700, true);
        $app->useBootstrapPath($path.'/bootstrap');
    }

    #[Override]
    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach ($this->isolatedDatabasePaths as $path) {
                (new Filesystem)->deleteDirectory($path);
            }

            $this->isolatedDatabasePaths = [];
        }
    }
}
