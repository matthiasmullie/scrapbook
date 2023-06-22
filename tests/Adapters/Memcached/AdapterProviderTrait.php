<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Memcached;

use MatthiasMullie\Scrapbook\Adapters\Memcached;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a bit of a workaround to ensure we only use 1 client
 * across all tests.
 * Traits are essentially copied into the classes they use, so
 * they can't otherwise share a static variable.
 */
function getMemcachedClient(): \Memcached
{
    static $client;
    if (!$client) {
        $client = new \Memcached();
        $client->addServer('memcached', 11211);
    }

    return $client;
}

trait AdapterProviderTrait
{
    public function getAdapterKeyValueStore(): KeyValueStore
    {
        if (!class_exists(\Memcached::class)) {
            throw new Exception('ext-memcached is not installed.');
        }

        return new Memcached(getMemcachedClient());
    }

    public function getCollectionName(): string
    {
        return 'collection';
    }
}
