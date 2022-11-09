<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook;

/**
 * Interface for key-value storage engines.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
interface KeyValueStore
{
    /**
     * Retrieves an item from the cache.
     *
     * Optionally, an 2nd variable can be passed to this function. It will be
     * filled with a value that can be used for cas()
     *
     * @param mixed $token Will be filled with the CAS token
     *
     * @return mixed|bool Value, or false on failure
     */
    public function get(string $key, mixed &$token = null): mixed;

    /**
     * Retrieves multiple items at once.
     *
     * Return value will be an associative array in [key => value] format. Keys
     * missing in cache will be omitted from the array.
     *
     * Optionally, an 2nd variable can be passed to this function. It will be
     * filled with values that can be used for cas(), in an associative array in
     * [key => token] format. Keys missing in cache will be omitted from the
     * array.
     *
     * getMulti is preferred over multiple individual get operations as you'll
     * get them all in 1 request.
     *
     * @param mixed[]|null $tokens Will be filled with the CAS tokens, in [key => token] format
     *
     * @return mixed[] [key => value]
     */
    public function getMulti(array $keys, array &$tokens = null): array;

    /**
     * Stores a value, regardless of whether or not the key already exists (in
     * which case it will overwrite the existing value for that key).
     *
     * Return value is a boolean true when the operation succeeds, or false on
     * failure.
     *
     * @param int $expire Time when item falls out of the cache:
     *                    0 = permanent (doesn't expires);
     *                    under 2592000 (30 days) = relative time, in seconds from now;
     *                    over 2592000 = absolute time, unix timestamp
     */
    public function set(string $key, mixed $value, int $expire = 0): bool;

    /**
     * Store multiple values at once.
     *
     * Return value will be an associative array in [key => status] form, where
     * status is a boolean true for success, or false for failure.
     *
     * setMulti is preferred over multiple individual set operations as you'll
     * set them all in 1 request.
     *
     * @param mixed[] $items  [key => value]
     * @param int     $expire Time when item falls out of the cache:
     *                        0 = permanent (doesn't expires);
     *                        under 2592000 (30 days) = relative time, in seconds from now;
     *                        over 2592000 = absolute time, unix timestamp
     *
     * @return bool[]
     */
    public function setMulti(array $items, int $expire = 0): array;

    /**
     * Deletes an item from the cache.
     * Returns true if item existed & was successfully deleted, false otherwise.
     *
     * Return value is a boolean true when the operation succeeds, or false on
     * failure.
     */
    public function delete(string $key): bool;

    /**
     * Deletes multiple items at once (reduced network traffic compared to
     * individual operations).
     *
     * Return value will be an associative array in [key => status] form, where
     * status is a boolean true for success, or false for failure.
     *
     * @param string[] $keys
     *
     * @return bool[]
     */
    public function deleteMulti(array $keys): array;

    /**
     * Adds an item under new key.
     *
     * This operation fails (returns false) if the key already exists in cache.
     * If the operation succeeds, true will be returned.
     *
     * @param int $expire Time when item falls out of the cache:
     *                    0 = permanent (doesn't expires);
     *                    under 2592000 (30 days) = relative time, in seconds from now;
     *                    over 2592000 = absolute time, unix timestamp
     */
    public function add(string $key, mixed $value, int $expire = 0): bool;

    /**
     * Replaces an item.
     *
     * This operation fails (returns false) if the key does not yet exist in
     * cache. If the operation succeeds, true will be returned.
     *
     * @param int $expire Time when item falls out of the cache:
     *                    0 = permanent (doesn't expires);
     *                    under 2592000 (30 days) = relative time, in seconds from now;
     *                    over 2592000 = absolute time, unix timestamp
     */
    public function replace(string $key, mixed $value, int $expire = 0): bool;

    /**
     * Replaces an item in 1 atomic operation, to ensure it didn't change since
     * it was originally read, when the CAS token was issued.
     *
     * This operation fails (returns false) if the CAS token didn't match with
     * what's currently in cache, when a new value has been written to cache
     * after we've fetched it. If the operation succeeds, true will be returned.
     *
     * @param mixed $token  Token received from get() or getMulti()
     * @param int   $expire Time when item falls out of the cache:
     *                      0 = permanent (doesn't expires);
     *                      under 2592000 (30 days) = relative time, in seconds from now;
     *                      over 2592000 = absolute time, unix timestamp
     */
    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool;

    /**
     * Increments a counter value, or sets an initial value if it does not yet
     * exist.
     *
     * The new counter value will be returned if this operation succeeds, or
     * false for failure (e.g. when the value currently in cache is not a
     * number, in which case it can't be incremented)
     *
     * @param int $offset  Value to increment with
     * @param int $initial Initial value (if item doesn't yet exist)
     * @param int $expire  Time when item falls out of the cache:
     *                     0 = permanent (doesn't expires);
     *                     under 2592000 (30 days) = relative time, in seconds from now;
     *                     over 2592000 = absolute time, unix timestamp
     *
     * @return int|false New value or false on failure
     */
    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false;

    /**
     * Decrements a counter value, or sets an initial value if it does not yet
     * exist.
     *
     * The new counter value will be returned if this operation succeeds, or
     * false for failure (e.g. when the value currently in cache is not a
     * number, in which case it can't be decremented)
     *
     * @param int $offset  Value to decrement with
     * @param int $initial Initial value (if item doesn't yet exist)
     * @param int $expire  Time when item falls out of the cache:
     *                     0 = permanent (doesn't expires);
     *                     under 2592000 (30 days) = relative time, in seconds from now;
     *                     over 2592000 = absolute time, unix timestamp
     *
     * @return int|false New value or false on failure
     */
    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false;

    /**
     * Updates an item's expiration time without altering the stored value.
     *
     * Return value is a boolean true when the operation succeeds, or false on
     * failure.
     *
     * @param int $expire Time when item falls out of the cache:
     *                    0 = permanent (doesn't expires);
     *                    under 2592000 (30 days) = relative time, in seconds from now;
     *                    over 2592000 = absolute time, unix timestamp
     */
    public function touch(string $key, int $expire): bool;

    /**
     * Clears the entire cache (or the everything for the given collection).
     *
     * Return value is a boolean true when the operation succeeds, or false on
     * failure.
     */
    public function flush(): bool;

    /**
     * Returns an isolated subset (collection) in which to store or fetch data
     * from.
     *
     * A new KeyValueStore object will be returned, one that will only have
     * access to this particular subset of data. Exact implementation can vary
     * between adapters (e.g. separate database, prefixed keys, ...), but it
     * will only ever provide access to data within this collection.
     *
     * It is not possible to set/fetch data across collections.
     * Setting the same key in 2 different collections will store 2 different
     * values, that can only be retrieved from their respective collections.
     * Flushing a collection will only flush those specific keys and will leave
     * keys in other collections untouched.
     * Flushing the server, however, will wipe out everything, including data in
     * any of the collections on that server.
     *
     * @return KeyValueStore A new KeyValueStore instance representing only a
     *                       subset of data on this server
     */
    public function getCollection(string $name): KeyValueStore;
}
