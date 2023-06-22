<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Redis;

use MatthiasMullie\Scrapbook\Adapters\Redis;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a bit of a workaround to ensure we only use 1 client
 * across all tests.
 * Traits are essentially copied into the classes they use, so
 * they can't otherwise share a static variable.
 */
function getRedisClient(): \Redis
{
    static $client;
    if (!$client) {
        $client = new \Redis();
        $client->connect('redis', 6379);
    }

    return $client;
}

trait AdapterProviderTrait
{
    public function getAdapterKeyValueStore(): KeyValueStore
    {
        if (!class_exists(\Redis::class)) {
            throw new Exception('ext-redis is not installed.');
        }

        return new Redis(getRedisClient());
    }

    public function getCollectionName(): string
    {
        // Redis databases are numeric
        return '1';
    }
}
