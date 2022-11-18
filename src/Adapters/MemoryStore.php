<?php

declare(strict_types=1);

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
    public array $items = [];

    protected int $limit = 0;

    protected int $size = 0;

    /**
     * @param int|string|null $limit Memory limit in bytes (defaults to 10% of memory_limit)
     */
    public function __construct(int|string $limit = null)
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

    public function get(string $key, mixed &$token = null): mixed
    {
        if (!$this->exists($key)) {
            $token = null;

            return false;
        }

        $value = $this->items[$key][0];

        // use serialized version of stored value as CAS token
        $token = $value;

        return unserialize($value, ['allowed_classes' => true]);
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        $items = [];
        $tokens = [];

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

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        $this->size -= isset($this->items[$key]) ? strlen($this->items[$key][0]) : 0;

        $value = serialize($value);
        $expire = $this->normalizeTime($expire);
        $this->items[$key] = [$value, $expire];

        $this->size += strlen($value);
        $this->lru($key);
        $this->evict();

        return true;
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        $success = [];
        foreach ($items as $key => $value) {
            // PHP treats numeric keys as integers, but they're allowed
            $key = (string) $key;
            $success[$key] = $this->set($key, $value, $expire);
        }

        return $success;
    }

    public function delete(string $key): bool
    {
        $exists = $this->exists($key);

        if ($exists) {
            $this->size -= strlen($this->items[$key][0]);
            unset($this->items[$key]);
        }

        return $exists;
    }

    public function deleteMulti(array $keys): array
    {
        $success = [];

        foreach ($keys as $key) {
            $success[$key] = $this->delete($key);
        }

        return $success;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        if ($this->exists($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        if (!$this->exists($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
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

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    public function touch(string $key, int $expire): bool
    {
        $expire = $this->normalizeTime($expire);

        // get current value & re-save it, with new expiration
        $value = $this->get($key, $token);

        return $this->cas($token, $key, $value, $expire);
    }

    public function flush(): bool
    {
        $this->items = [];
        $this->size = 0;

        return true;
    }

    public function getCollection(string $name): KeyValueStore
    {
        return new Collection($this, $name);
    }

    /**
     * Checks if a value exists in cache and is not yet expired.
     */
    protected function exists(string $key): bool
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
     */
    protected function doIncrement(string $key, int $offset, int $initial, int $expire): int|false
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
     */
    protected function normalizeTime(int $time): int
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
    protected function lru(string $key): void
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
    protected function evict(): void
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
     */
    protected function shorthandToBytes(string|int $shorthand): int
    {
        if (is_numeric($shorthand)) {
            // make sure that when float(1.234E17) is passed in, it doesn't get
            // cast to string('1.234E17'), then to int(1)
            return $shorthand;
        }

        $units = ['B' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3];

        return (int) preg_replace_callback(
            '/^([0-9]+)(' . implode('|', array_keys($units)) . ')$/',
            static function ($match) use ($units): int {
                return $match[1] * $units[$match[2]];
            },
            $shorthand
        );
    }
}
