<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MySQL;

use MatthiasMullie\Scrapbook\Adapters\MySQL;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a bit of a workaround to ensure we only use 1 client
 * across all tests.
 * Traits are essentially copied into the classes they use, so
 * they can't otherwise share a static variable.
 */
function getMySQLClient(): \PDO
{
    static $client;
    if (!$client) {
        $client = new \PDO('mysql:host=mysql;port=3306;dbname=cache', 'user', 'pass');
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

        return new MySQL(getMySQLClient());
    }

    public function getCollectionName(): string
    {
        return 'collection';
    }
}
