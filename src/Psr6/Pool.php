<?php

namespace MatthiasMullie\Scrapbook\Psr6;

use Cache\Taggable\TaggablePoolInterface;
use Cache\Taggable\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Representation of the cache storage, which lets you read items from, and add
 * values to the cache.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Pool implements CacheItemPoolInterface, TaggablePoolInterface
{
    use TaggablePoolTrait;

    /**
     * List of invalid (or reserved) key characters.
     *
     * @var string
     */
    const KEY_INVALID_CHARACTERS = '{}()/\@:';

    /**
     * @var KeyValueStore
     */
    protected $store;

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var Item[]
     */
    protected $deferred = array();

    /**
     * @param KeyValueStore $store
     */
    public function __construct(KeyValueStore $store)
    {
        $this->store = $store;
        $this->repository = new Repository($store);
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key, array $tags = [])
    {
        $this->assertValidKey($key);
        $taggedKey = $this->generateCacheKey($key, $tags);

        return $this->getItemWithoutGenerateCacheKey($taggedKey);
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemWithoutGenerateCacheKey($key)
    {
        if (array_key_exists($key, $this->deferred)) {
            /*
             * In theory, we could request & change a deferred value. In the
             * case of objects, we'll clone them to make sure that when the
             * value for 1 item is manipulated, it doesn't affect the value of
             * the one about to be stored to cache (because those objects would
             * be passed by-ref without the cloning)
             */
            $value = $this->deferred[$key];

            return is_object($value) ? clone $value : $value;
        }

        // return a stub object - the real call to the cache store will only be
        // done once we actually want to access data from this object
        return new Item($key, $this->repository);
    }

    /**
     * {@inheritdoc}
     *
     * @return Item[]
     */
    public function getItems(array $keys = [], array $tags = [])
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key, $tags);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key, array $tags = [])
    {
        return $this->getItem($key, $tags)->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(array $tags = [])
    {
        $this->deferred = [];

        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $this->flushTag($tag);
            }

            return true;
        }

        return $this->store->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key, array $tags = [])
    {
        $this->assertValidKey($key);
        $taggedKey = $this->generateCacheKey($key, $tags);

        return $this->store->delete($taggedKey);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys, array $tags = [])
    {
        $taggedKeys = [];
        foreach ($keys as $key) {
            $this->assertValidKey($key);
            $taggedKey = $this->generateCacheKey($key, $tags);
            $taggedKeys[] = $taggedKey;
            unset($this->deferred[$taggedKey]);
        }

        $success = $this->store->deleteMulti($taggedKeys);

        return !in_array(false, $success);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof Item) {
            throw new InvalidArgumentException(
                'MatthiasMullie\Scrapbook\Psr6\Pool can only save
                MatthiasMullie\Scrapbook\Psr6\Item objects'
            );
        }

        $expire = $item->getExpiration();
        if ($expire !== 0 && $expire < time()) {
            // already expired: don't even save it
            return true;
        }

        return $this->store->set($item->getTaggedKey(), $item->get(), $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof Item) {
            throw new InvalidArgumentException(
                'MatthiasMullie\Scrapbook\Psr6\Pool can only save
                MatthiasMullie\Scrapbook\Psr6\Item objects'
            );
        }

        $this->deferred[$item->getTaggedKey()] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $deferred = [];
        foreach ($this->deferred as $key => $item) {
            $expire = $item->getExpiration();

            if ($expire !== 0 && $expire < time()) {
                // already expired: don't even save it
                continue;
            }

            // setMulti doesn't allow to set expiration times on a per-item basis,
            // so we'll have to group our requests per expiration date
            $deferred[$expire][$item->getTaggedKey()] = $item->get();
        }

        // setMulti doesn't allow to set expiration times on a per-item basis,
        // so we'll have to group our requests per expiration date
        $success = true;
        foreach ($deferred as $expire => $items) {
            $status = $this->store->setMulti($items, $expire);
            $success &= !in_array(false, $status);
            unset($deferred[$expire]);
        }

        return (bool) $success;
    }

    /**
     * Throws an exception if $key is invalid.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function assertValidKey($key)
    {
        // valid key according to PSR-6 rules
        $invalid = preg_quote(static::KEY_INVALID_CHARACTERS, '/');
        if (preg_match('/['.$invalid.']/', $key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.$key.'. Contains (a) character(s) reserved '.
                'for future extension: '.static::KEY_INVALID_CHARACTERS
            );
        }
    }

    protected function validateTagName($name)
    {
        $this->assertValidKey($name);
    }
}
