<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\Adapters\Collections\MemoryStore as Collection;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * No-storage cache: all values will be "cached" in memory, in a simple PHP
 * array. Values will only be valid for 1 request: whatever is in memory at the
 * end of the request just dies. Other requests will start from a blank slate.
 *
 * This is mainly useful for testing purposes, where this class can let you test
 * application logic against cache, without having to run a cache server.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class MemoryStore implements KeyValueStore
{
    /**
     * @var array
     */
    protected $items = array();

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var int
     */
    protected $size = 0;

    /**
     * @param int|string $limit Memory limit in bytes (defaults to 10% of memory_limit)
     */
    public function __construct($limit = null)
    {
        if ($limit === null) {
            $phpLimit = ini_get('memory_limit');
            if ($phpLimit <= 0) {
                $this->limit = PHP_INT_MAX;
            } else {
                $this->limit = (int) ($this->shorthandToBytes($phpLimit) / 10);
            }
        } else {
            $this->limit = $this->shorthandToBytes($limit);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        if (!$this->exists($key)) {
            $token = null;

            return false;
        }

        $value = $this->items[$key][0];

        // use serialized version of stored value as CAS token
        $token = $value;

        return unserialize($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $items = array();
        $tokens = array();

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
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $this->size -= isset($this->items[$key]) ? strlen($this->items[$key][0]) : 0;

        $value = serialize($value);
        $expire = $this->normalizeTime($expire);
        $this->items[$key] = array($value, $expire);

        $this->size += strlen($value);
        $this->lru($key);
        $this->evict();

        return true;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $exists = $this->exists($key);

        if ($exists) {
            $this->size -= strlen($this->items[$key][0]);
            unset($this->items[$key]);
        }

        return $exists;
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        if ($this->exists($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        if (!$this->exists($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $expire = $this->normalizeTime($expire);

        // get current value & re-save it, with new expiration
        $value = $this->get($key, $token);

        return $this->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->items = array();
        $this->size = 0;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        return new Collection($this, $name);
    }

    /**
     * Checks if a value exists in cache and is not yet expired.
     *
     * @param string $key
     *
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
            $this->size -= strlen($this->items[$key][0]);
            unset($this->items[$key]);

            return false;
        }

        $this->lru($key);

        return true;
    }

    /**
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * split up (increment won't accept negative values).
     *
     * @param string $key
     * @param int    $offset
     * @param int    $initial
     * @param int    $expire
     *
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
     * * 0, for infinity.
     *
     * The first case (relative time) will be normalized into a fixed absolute
     * timestamp.
     *
     * @param int $time
     *
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

    /**
     * This cache uses least recently used algorithm. This is to be called
     * with the key to be marked as just used.
     */
    protected function lru($key)
    {
        // move key that has just been used to last position in the array
        $value = $this->items[$key];
        unset($this->items[$key]);
        $this->items[$key] = $value;
    }

    /**
     * Least recently used cache values will be evicted from cache should
     * it fill up too much.
     */
    protected function evict()
    {
        while ($this->size > $this->limit && !empty($this->items)) {
            $item = array_shift($this->items);
            $this->size -= strlen($item[0]);
        }
    }

    /**
     * Understands shorthand byte values (as used in e.g. memory_limit ini
     * setting) and converts them into bytes.
     *
     * @see http://php.net/manual/en/faq.using.php#faq.using.shorthandbytes
     *
     * @param string|int $shorthand Amount of bytes (int) or shorthand value (e.g. 512M)
     *
     * @return int
     */
    protected function shorthandToBytes($shorthand)
    {
        if (is_numeric($shorthand)) {
            // make sure that when float(1.234E17) is passed in, it doesn't get
            // cast to string('1.234E17'), then to int(1)
            return $shorthand;
        }

        $units = array('B' => 1024, 'M' => pow(1024, 2), 'G' => pow(1024, 3));

        return (int) preg_replace_callback('/^([0-9]+)('.implode(array_keys($units), '|').')$/', function ($match) use ($units) {
            return $match[1] * $units[$match[2]];
        }, $shorthand);
    }
}
