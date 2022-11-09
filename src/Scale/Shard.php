<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Scale;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This class lets you scale your cache cluster by sharding the data across
 * multiple cache servers.
 *
 * Pass the individual KeyValueStore objects that compose the cache server pool
 * into this constructor how you want the data to be sharded. The cache data
 * will be sharded over them according to the order they were in when they were
 * passed into this constructor (so make sure to always keep the order the same)
 *
 * The sharding is spread evenly and all cache servers will roughly receive the
 * same amount of cache keys. If some servers are bigger than others, you can
 * offset this by adding the KeyValueStore object more than once.
 *
 * Data can even be sharded among different adapters: one server in the shard
 * pool can be Redis while another can be Memcached. Not sure why you would even
 * want that, but you could!
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Shard implements KeyValueStore
{
    /**
     * @var KeyValueStore[]
     */
    protected array $caches = [];

    /**
     * Overloadable with multiple KeyValueStore objects.
     */
    public function __construct(KeyValueStore $cache1, KeyValueStore $cache2 = null /* , [KeyValueStore $cache3, [...]] */)
    {
        $caches = func_get_args();
        $caches = array_filter($caches);
        $this->caches = $caches;
    }

    public function addCache(KeyValueStore $cache): void
    {
        $this->caches[] = $cache;
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        return $this->getShard($key)->get($key, $token);
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        $shards = $this->getShards($keys);
        $results = [];
        $tokens = [];

        /** @var KeyValueStore $shard */
        foreach ($shards as $shard) {
            $keysOnShard = $shards[$shard];
            $results += $shard->getMulti($keysOnShard, $shardTokens);
            $tokens += $shardTokens ?: [];
        }

        return $results;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        return $this->getShard($key)->set($key, $value, $expire);
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        $shards = $this->getShards(array_keys($items));
        $results = [];

        /** @var KeyValueStore $shard */
        foreach ($shards as $shard) {
            $keysOnShard = $shards[$shard];
            $itemsOnShard = array_intersect_key($items, array_flip($keysOnShard));
            $results += $shard->setMulti($itemsOnShard, $expire);
        }

        return $results;
    }

    public function delete(string $key): bool
    {
        return $this->getShard($key)->delete($key);
    }

    public function deleteMulti(array $keys): array
    {
        $shards = $this->getShards($keys);
        $results = [];

        /** @var KeyValueStore $shard */
        foreach ($shards as $shard) {
            $keysOnShard = $shards[$shard];
            $results += $shard->deleteMulti($keysOnShard);
        }

        return $results;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        return $this->getShard($key)->add($key, $value, $expire);
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        return $this->getShard($key)->replace($key, $value, $expire);
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        return $this->getShard($key)->cas($token, $key, $value, $expire);
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        return $this->getShard($key)->increment($key, $offset, $initial, $expire);
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        return $this->getShard($key)->decrement($key, $offset, $initial, $expire);
    }

    public function touch(string $key, int $expire): bool
    {
        return $this->getShard($key)->touch($key, $expire);
    }

    public function flush(): bool
    {
        $result = true;

        foreach ($this->caches as $cache) {
            $result = $result && $cache->flush();
        }

        return $result;
    }

    public function getCollection(string $name): KeyValueStore
    {
        $shard = new static($this->caches[0]->getCollection($name));

        $count = count($this->caches);
        for ($i = 1; $i < $count; ++$i) {
            $shard->addCache($this->caches[$i]->getCollection($name));
        }

        return $shard;
    }

    /**
     * Get the shard (KeyValueStore object) that corresponds to a particular
     * cache key.
     */
    protected function getShard(string $key): KeyValueStore
    {
        /*
         * The hash is so we can deterministically randomize the spread of keys
         * over servers: if we were to just spread them based on key name, we
         * may end up with a large chunk of similarly prefixed keys on the same
         * server. Hashing the key will ensure similar looking keys can still
         * result in very different values, yet they result will be the same
         * every time it's repeated for the same key.
         * Since we don't use the hash for encryption, the fastest algorithm
         * will do just fine here.
         */
        $hash = crc32($key);

        // crc32 on 32-bit machines can produce a negative int
        $hash = abs($hash);

        $index = $hash % count($this->caches);

        return $this->caches[$index];
    }

    /**
     * Get a [KeyValueStore => array of cache keys] map (SplObjectStorage) for
     * multiple cache keys.
     */
    protected function getShards(array $keys): \SplObjectStorage
    {
        $shards = new \SplObjectStorage();

        foreach ($keys as $key) {
            // PHP treats numeric keys as integers, but they're allowed
            $key = (string) $key;
            $shard = $this->getShard($key);
            if (!isset($shards[$shard])) {
                $shards[$shard] = [];
            }

            $shards[$shard] = array_merge($shards[$shard], [$key]);
        }

        return $shards;
    }
}
