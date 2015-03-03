<?php
namespace Scrapbook\Adapters;

use Scrapbook\Cache\KeyValueStore;

/**
 * No-storage cache: all values will be "cached" in memory, in a simple PHP
 * array. Values will only be valid for 1 request: whatever is in memory at the
 * end of the request just dies. Other requests will start from a blank slate.
 *
 * This is mainly useful for testing purposes, where this class can let you test
 * application logic against cache, without having to run a cache server.
 *
 * This could be part of scrapbook/key-value-adapters, but it makes more sense
 * to bundle this along with the interface, so 3rd parties implementing against
 * this interface have something to (write) test(s) against, without actually
 * having to set up a cache server & bring in an adapter.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 *
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class MemoryStore implements KeyValueStore
{
    /**
     * @var array
     */
    protected $items = array();

    /**
     * {@inheritDoc}
     */
    public function get($key, &$token = null)
    {
        if (!$this->exists($key)) {
            return false;
        }

        $value = $this->items[$key][0];

        // use serialized version of stored value as CAS token
        $token = $value;

        return unserialize($value);
    }

    /**
     * {@inheritDoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $items = array();

        foreach ($keys as $key) {
            if (!$this->exists($key)) {
                // omit missing keys from return array
                continue;
            }

            $items[$key] = $this->get($key, $token);
            $tokens[$key] = $token;
        }

        return $items;
    }

    /**
     * {@inheritDoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $value = serialize($value);
        $expire = $this->normalizeTime($expire);
        $this->items[$key] = array($value, $expire);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $success = array();
        foreach ($items as $key => $value) {
            $success[$key] = $this->set($key, $value, $expire);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($key)
    {
        $exists = $this->exists($key);

        unset($this->items[$key]);

        return $exists;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteMulti(array $keys)
    {
        $success = array();

        foreach ($keys as $key) {
            $success[$key] = $this->delete($key);
        }

        return $success;
    }

    /**
     * {@inheritDoc}
     */
    public function add($key, $value, $expire = 0)
    {
        if ($this->exists($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * {@inheritDoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        if (!$this->exists($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * {@inheritDoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        if (!$this->exists($key)) {
            return false;
        }

        $this->get($key, $comparison);
        if ($comparison !== $token) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * {@inheritDoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritDoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritDoc}
     */
    public function touch($key, $expire)
    {
        $expire = $this->normalizeTime($expire);

        // get current value & re-save it, with new expiration
        $value = $this->get($key, $token);

        return $this->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritDoc}
     */
    public function flush()
    {
        $this->items = array();

        return true;
    }

    /**
     * Checks if a value exists in cache and is not yet expired.
     *
     * @param  string $key
     * @return bool
     */
    protected function exists($key)
    {
        if (!array_key_exists($key, $this->items)) {
            // key not in cache
            return false;
        }

        $expire = $this->items[$key][1];
        if ($expire !== 0 && $expire < time()) {
            // not permanent & already expired
            return false;
        }

        return true;
    }

    /**
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * split up (increment won't accept negative values)
     *
     * @param  string   $key
     * @param  int      $offset
     * @param  int      $initial
     * @param  int      $expire
     * @return int|bool
     */
    protected function doIncrement($key, $offset, $initial, $expire)
    {
        if (!$this->exists($key)) {
            $this->set($key, $initial, $expire);

            return $initial;
        }

        $value = $this->get($key);
        if (!is_numeric($value) || $value < 0) {
            return false;
        }

        $value += $offset;
        // value can never be lower than 0
        $value = max(0, $value);
        $this->set($key, $value, $expire);

        return $value;
    }

    /**
     * Times can be:
     * * relative (in seconds) to current time, within 30 days
     * * absolute unix timestamp
     * * 0, for infinity
     *
     * The first case (relative time) will be normalized into a fixed absolute
     * timestamp.
     *
     * @param  int $time
     * @return int
     */
    protected function normalizeTime($time)
    {
        // 0 = infinity
        if (!$time) {
            return 0;
        }

        // relative time in seconds, <30 days
        if ($time < 30 * 24 * 60 * 60) {
            $time += time();
        }

        return $time;
    }
}
