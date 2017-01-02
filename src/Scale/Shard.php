<?php

namespace MatthiasMullie\Scrapbook\Scale;

use MatthiasMullie\Scrapbook\KeyValueStore;
use SplObjectStorage;

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
    protected $caches = array();

    /**
     * Overloadable with multiple KeyValueStore objects.
     *
     * @param KeyValueStore      $cache1
     * @param KeyValueStore|null $cache2
     */
    public function __construct(KeyValueStore $cache1, KeyValueStore $cache2 = null /* , [KeyValueStore $cache3, [...]] */)
    {
        $caches = func_get_args();
        $caches = array_filter($caches);
        $this->caches = $caches;
    }

    /**
     * @param KeyValueStore $cache
     */
    public function addCache(KeyValueStore $cache)
    {
        $this->caches[] = $cache;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        return $this->getShard($key)->get($key, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $shards = $this->getShards($keys);
        $results = array();
        $tokens = array();

        /** @var KeyValueStore $shard */
        foreach ($shards as $shard) {
            $keysOnShard = $shards[$shard];
            $results += $shard->getMulti($keysOnShard, $shardTokens);
            $tokens += $shardTokens ?: array();
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->getShard($key)->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $shards = $this->getShards(array_keys($items));
        $results = array();

        /** @var KeyValueStore $shard */
        foreach ($shards as $shard) {
            $keysOnShard = $shards[$shard];
            $itemsOnShard = array_intersect_key($items, array_flip($keysOnShard));
            $results += $shard->setMulti($itemsOnShard, $expire);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->getShard($key)->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        $shards = $this->getShards($keys);
        $results = array();

        /** @var KeyValueStore $shard */
        foreach ($shards as $shard) {
            $keysOnShard = $shards[$shard];
            $results += $shard->deleteMulti($keysOnShard);
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        return $this->getShard($key)->add($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        return $this->getShard($key)->replace($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        return $this->getShard($key)->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        return $this->getShard($key)->increment($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        return $this->getShard($key)->decrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        return $this->getShard($key)->touch($key, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $result = true;

        foreach ($this->caches as $cache) {
            $result &= $cache->flush();
        }

        return (bool) $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
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
     *
     * @param string $key
     *
     * @return KeyValueStore
     */
    protected function getShard($key)
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
     *
     * @param array $keys
     *
     * @return SplObjectStorage
     */
    protected function getShards(array $keys)
    {
        $shards = new SplObjectStorage();

        foreach ($keys as $key) {
            $shard = $this->getShard($key);
            if (!isset($shards[$shard])) {
                $shards[$shard] = array();
            }

            $shards[$shard] = array_merge($shards[$shard], array($key));
        }

        return $shards;
    }
}
