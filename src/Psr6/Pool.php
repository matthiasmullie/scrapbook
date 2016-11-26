<?php

namespace MatthiasMullie\Scrapbook\Psr6;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Representation of the cache storage, which lets you read items from, and add
 * values to the cache.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Pool implements CacheItemPoolInterface
{
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

    public function __destruct()
    {
        // make sure all deferred items are actually saved
        $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $this->assertValidKey($key);
        if (array_key_exists($key, $this->deferred)) {
            /*
             * In theory, we could request & change a deferred value. In the
             * case of objects, we'll clone them to make sure that when the
             * value for 1 item is manipulated, it doesn't affect the value of
             * the one about to be stored to cache (because those objects would
             * be passed by-ref without the cloning)
             */
            $value = $this->deferred[$key];
            $item = is_object($value) ? clone $value : $value;

            /*
             * Deferred items should identify as being hit, unless if expired:
             * @see https://groups.google.com/forum/?fromgroups#!topic/php-fig/pxy_VYgm2sU
             */
            $item->overrideIsHit(!$item->isExpired());

            return $item;
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
    public function getItems(array $keys = array())
    {
        $items = array();
        foreach ($keys as $key) {
            $this->assertValidKey($key);

            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $this->assertValidKey($key);

        $item = $this->getItem($key);

        return $item->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = array();

        return $this->store->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $this->assertValidKey($key);

        $this->store->delete($key);
        unset($this->deferred[$key]);

        // as long as the item is gone from the cache (even if it never existed
        // and delete failed because of that), we should return `true`
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->assertValidKey($key);

            unset($this->deferred[$key]);
        }

        $this->store->deleteMulti($keys);

        // as long as the item is gone from the cache (even if it never existed
        // and delete failed because of that), we should return `true`
        return true;
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

        if (!$item->hasChanged()) {
            /*
             * If the item didn't change, we don't have to re-save it. We do,
             * however, need to check if the item actually holds a value: if it
             * does, it should be considered "saved" (even though nothing has
             * changed, the value for this key is in cache) and if it doesn't,
             * well then nothing is in cache.
             */
            return $item->get() !== null;
        }

        $expire = $item->getExpiration();

        return $this->store->set($item->getKey(), $item->get(), $expire);
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

        $this->deferred[$item->getKey()] = $item;
        // let's pretend that this actually comes from cache (we'll store it
        // there soon), unless if it's already expired (in which case it will
        // never reach cache...)
        $item->overrideIsHit(!$item->isExpired());

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $deferred = array();
        foreach ($this->deferred as $key => $item) {
            if ($item->isExpired()) {
                // already expired: don't even save it
                continue;
            }

            // setMulti doesn't allow to set expiration times on a per-item basis,
            // so we'll have to group our requests per expiration date
            $expire = $item->getExpiration();
            $deferred[$expire][$item->getKey()] = $item->get();
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
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.var_export($key, true).'. Key should be a string.'
            );
        }

        // valid key according to PSR-6 rules
        $invalid = preg_quote(static::KEY_INVALID_CHARACTERS, '/');
        if (preg_match('/['.$invalid.']/', $key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.$key.'. Contains (a) character(s) reserved '.
                'for future extension: '.static::KEY_INVALID_CHARACTERS
            );
        }
    }
}
