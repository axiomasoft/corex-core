<?php

declare(strict_types=1);

namespace CoreX\Testing;

use CoreX\CoreServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Reusable Testbench base for CoreX and downstream module/product test suites.
 */
abstract class CoreXTestCase extends Orchestra
{
    use IsolatesDatabasePath;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CoreServiceProvider::class,
        ];
    }

    /** @param Application $app */
    protected function defineEnvironment($app): void
    {
        // Testbench ships no APP_KEY; Illuminate's encrypter (is_sensitive
        // settings) throws MissingAppKeyException without one. Fixed
        // test-only key — never used outside this harness.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // pg-lane: additive — the default connection stays sqlite
        // above; tests tagged ->group('pg') opt in with
        // config(['database.default' => 'pgsql']). getenv(), not the env()
        // helper (larastan.noEnvCallsOutsideOfConfig — this package ships no
        // config/database.php for these to live in).
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => getenv('PGSQL_DATABASE') ?: 'corex_test',
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
        ]);

        // sys_audit_log is PG-only — HasAuditLog fires
        // unconditionally on every Auditable model's create/update/delete,
        // so leaving it enabled would break every sqlite-lane test that
        // touches an Auditable model (Widget et al.) with a missing-table
        // error. Audit-specific tests opt back in explicitly (->group('pg')
        // + config(['corex.audit.enabled' => true])).
        $app['config']->set('corex.audit.enabled', false);
    }
}
