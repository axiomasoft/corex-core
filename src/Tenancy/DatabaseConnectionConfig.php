<?php

declare(strict_types=1);

namespace CoreX\Tenancy;

/**
 * Full Laravel connection config for an account's tenant database, resolved
 * from root_clusters+db_name.
 *
 * @internal spec: B-11 §3.1 (owner: B-11)
 */
final readonly class DatabaseConnectionConfig
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $driver,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public array $options = [],
    ) {}
}
