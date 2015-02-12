<?php
namespace Scrapbook\Cache;

/**
 * Interface for key-value storage engines.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 *
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
interface KeyValueStore
{
    /**
     * Retrieves an item from the cache.
     *
     * @param  string     $key
     * @param  mixed      $token Will be filled with the CAS token
     * @return mixed|bool Value, or false on failure
     */
    public function get($key, &$token = null);

    /**
     * Retrieves multiple items at once (reduced network traffic compared to
     * individual operations)
     *
     * Return value will be an associative array. Keys missing in cache will be
     * omitted from the array.
     *
     * @param  array   $keys
     * @param  mixed[] $tokens Will be filled with the CAS tokens
     * @return mixed[] [key => value]
     */
    public function getMulti(array $keys, array &$tokens = null);

    /**
     * Stores an item, regardless of whether or not it already exists.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $expire 0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return bool
     */
    public function set($key, $value, $expire = 0);

    /**
     * Store multiple items at once (reduced network traffic compared to
     * individual operations)
     *
     * @param  mixed[] $items  [key => value]
     * @param  int     $expire 0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return bool
     */
    public function setMulti(array $items, $expire = 0);

    /**
     * Deletes an item from the cache.
     * Returns true if item existed & was successfully deleted, false otherwise.
     *
     * @param  string $key
     * @return bool
     */
    public function delete($key);

    /**
     * Deletes multiple items at once (reduced network traffic compared to
     * individual operations)
     *
     * @param  array $keys
     * @return bool
     */
    public function deleteMulti(array $keys);

    /**
     * Adds an item under new key.
     * Operation fails (returns false) if key already exists on server.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $expire 0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return bool
     */
    public function add($key, $value, $expire = 0);

    /**
     * Replaces an item.
     * Operation fails (returns false) if key does not yet exist on server.
     *
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $expire 0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return bool
     */
    public function replace($key, $value, $expire = 0);

    /**
     * Replaces an item in 1 atomic operation, to ensure it didn't change since
     * it was originally read (= when the CAS token was issued)
     * Operation fails (returns false) if CAS token didn't match.
     *
     * @param  mixed  $token  Token received from get() or getMulti()
     * @param  string $key
     * @param  mixed  $value
     * @param  int    $expire 0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return bool
     */
    public function cas($token, $key, $value, $expire = 0);

    /**
     * Increments a counter value.
     *
     * @param  string   $key
     * @param  int      $offset  Value to increment with
     * @param  int      $initial Initial value (if item doesn't yet exist)
     * @param  int      $expire  0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return int|bool New value or false on failure
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0);

    /**
     * Decrements a counter value.
     *
     * @param  string   $key
     * @param  int      $offset  Value to decrement with
     * @param  int      $initial Initial value (if item doesn't yet exist)
     * @param  int      $expire  0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return int|bool New value or false on failure
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0);

    /**
     * Updates an item's expiration time without altering the stored value.
     *
     * @param  string $key
     * @param  int    $expire 0 = permanent, <2592000 (30 days) = seconds from now, >2592000 = unix timestamp
     * @return bool
     */
    public function touch($key, $expire);

    /**
     * Clears the entire cache.
     *
     * @return bool
     */
    public function flush();
}
