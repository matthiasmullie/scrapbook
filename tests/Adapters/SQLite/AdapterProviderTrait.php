<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\SQLite;

use MatthiasMullie\Scrapbook\Adapters\SQLite;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a bit of a workaround to ensure we only use 1 client
 * across all tests.
 * Traits are essentially copied into the classes they use, so
 * they can't otherwise share a static variable.
 */
function getSQLiteClient(): \PDO
{
    static $client;
    if (!$client) {
        $client = new \PDO('sqlite::memory:');
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

        return new SQLite(getSQLiteClient());
    }

    public function getCollectionName(): string
    {
        return 'collection';
    }
}
