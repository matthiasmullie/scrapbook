<?php

namespace Psr\Cache;

/**
 * \Psr\Cache\CacheItemPoolInterface generates Cache\CacheItem objects.
 */
interface CacheItemPoolInterface
{
    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key
     *                    The key for which to return the corresponding Cache Item.
     *
     * @return \Psr\Cache\CacheItemInterface
     *                                       The corresponding Cache Item.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     *                                             If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *                                             MUST be thrown.
     */
    public function getItem($key);

    /**
     * Returns a traversable set of cache items.
     *
     * @param array $keys
     *                    An indexed array of keys of items to retrieve.
     *
     * @return array|\Traversable
     *                            A traversable collection of Cache Items keyed by the cache keys of
     *                            each item. A Cache item will be returned for each key, even if that
     *                            key is not found. However, if no keys are specified then an empty
     *                            traversable MUST be returned instead.
     */
    public function getItems(array $keys = array());

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     *              True if the pool was successfully cleared. False if there was an error.
     */
    public function clear();

    /**
     * Removes multiple items from the pool.
     *
     * @param array $keys
     *                    An array of keys that should be removed from the pool.
     *
     * @return static
     *                The invoked object.
     */
    public function deleteItems(array $keys);

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item
     *                                 The cache item to save.
     *
     * @return static
     *                The invoked object.
     */
    public function save(CacheItemInterface $item);

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item
     *                                 The cache item to save.
     *
     * @return static
     *                The invoked object.
     */
    public function saveDeferred(CacheItemInterface $item);

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     *              TRUE if all not-yet-saved items were successfully saved. FALSE otherwise.
     */
    public function commit();
}
