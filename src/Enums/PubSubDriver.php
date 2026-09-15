<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * `corex.pubsub.driver` — selects the PubSub implementation PubSubManager
 * delegates to (redis is the cloud default, database/null are the
 * boxed fallbacks when Redis is unavailable).
 *
 * @internal spec: B-10 §6.1
 */
enum PubSubDriver: string
{
    case Redis = 'redis';
    case Database = 'database';
    case Null = 'null';
}
