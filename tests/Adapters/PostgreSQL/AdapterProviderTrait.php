<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\PostgreSQL;

use MatthiasMullie\Scrapbook\Adapters\PostgreSQL;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a bit of a workaround to ensure we only use 1 client
 * across all tests.
 * Traits are essentially copied into the classes they use, so
 * they can't otherwise share a static variable.
 */
function getPostgreSQLClient(): \PDO
{
    static $client;
    if (!$client) {
        $client = new \PDO('pgsql:host=postgresql;port=5432;dbname=cache', 'user', 'pass');
    }

    return $client;
}

trait AdapterProviderTrait
{
    public function getAdapterKeyValueStore(): KeyValueStore
    {
        if (!class_exists(\PDO::class)) {
            throw new Exception('ext-pdo is not installed.');
        }

        return new PostgreSQL(getPostgreSQLClient());
    }

    public function getCollectionName(): string
    {
        return 'collection';
    }
}
