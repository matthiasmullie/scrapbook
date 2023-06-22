<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Psr16;

use MatthiasMullie\Scrapbook\KeyValueStore;
use Psr\SimpleCache\CacheInterface;

/**
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class SimpleCache implements CacheInterface
{
    /**
     * List of invalid (or reserved) key characters.
     *
     * @var string
     */
    protected const KEY_INVALID_CHARACTERS = '{}()/\@:';

    protected KeyValueStore $store;

    public function __construct(KeyValueStore $store)
    {
        $this->store = $store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->assertValidKey($key);

        // KeyValueStore::get returns false for cache misses (which could also
        // be confused for a `false` value), so we'll check existence with getMulti
        $multi = $this->store->getMulti([$key]);

        return $multi[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int|\DateInterval $ttl = null): bool
    {
        $this->assertValidKey($key);
        $ttl = $this->ttl($ttl);

        return $this->store->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $this->assertValidKey($key);

        $this->store->delete($key);

        // as long as the item is gone from the cache (even if it never existed
        // and delete failed because of that), we should return `true`
        return true;
    }

    public function clear(): bool
    {
        return $this->store->flush();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        if (!is_array($keys)) {
            throw new InvalidArgumentException('Invalid keys: ' . var_export($keys, true) . '. Keys should be an array or Traversable of strings.');
        }
        array_map([$this, 'assertValidKey'], $keys);

        $results = $this->store->getMulti($keys);

        // KeyValueStore omits values that are not in cache, while PSR-16 will
        // have them with a default value
        $nulls = array_fill_keys($keys, $default);

        return array_merge($nulls, $results);
    }

    public function setMultiple(iterable $values, int|\DateInterval $ttl = null): bool
    {
        if ($values instanceof \Traversable) {
            // we also need the keys, and an array is stricter about what it can
            // have as keys than a Traversable is, so we can't use
            // iterator_to_array...
            $array = [];
            foreach ($values as $key => $value) {
                if (!is_string($key) && !is_int($key)) {
                    throw new InvalidArgumentException('Invalid values: ' . var_export($values, true) . '. Only strings are allowed as keys.');
                }
                $array[$key] = $value;
            }
            $values = $array;
        }

        foreach ($values as $key => $value) {
            // $key is also allowed to be an integer, since ['0' => ...] will
            // automatically convert to [0 => ...]
            $key = is_int($key) ? (string) $key : $key;
            $this->assertValidKey($key);
        }

        $ttl = $this->ttl($ttl);
        $success = $this->store->setMulti($values, $ttl);

        return !in_array(false, $success, true);
    }

    public function deleteMultiple(iterable $keys): bool
    {
        if ($keys instanceof \Traversable) {
            $keys = iterator_to_array($keys, false);
        }

        array_map([$this, 'assertValidKey'], $keys);

        $this->store->deleteMulti($keys);

        // as long as the item is gone from the cache (even if it never existed
        // and delete failed because of that), we should return `true`
        return true;
    }

    public function has(string $key): bool
    {
        $this->assertValidKey($key);

        // KeyValueStore::get returns false for cache misses (which could also
        // be confused for a `false` value), so we'll check existence with getMulti
        $multi = $this->store->getMulti([$key]);

        return isset($multi[$key]);
    }

    /**
     * Throws an exception if $key is invalid.
     *
     * @throws InvalidArgumentException
     */
    protected function assertValidKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Invalid key. Key should not be empty.');
        }

        // valid key according to PSR-16 rules
        $invalid = preg_quote(static::KEY_INVALID_CHARACTERS, '/');
        if (preg_match('/[' . $invalid . ']/', $key)) {
            throw new InvalidArgumentException('Invalid key: ' . $key . '. Contains (a) character(s) reserved for future extension: ' . static::KEY_INVALID_CHARACTERS);
        }
    }

    /**
     * Accepts all TTL inputs valid in PSR-16 (null|int|DateInterval) and
     * converts them into TTL for KeyValueStore (int).
     */
    protected function ttl(null|int|\DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if ($ttl instanceof \DateInterval) {
            // convert DateInterval to integer by adding it to a 0 DateTime
            $datetime = new \DateTime();
            $datetime->setTimestamp(0);
            $datetime->add($ttl);
            $ttl = (int) $datetime->format('U');
        }

        /*
         * PSR-16 specifies that if `0` is provided, it must be treated as
         * expired, whereas KeyValueStore will interpret 0 to mean "never
         * expire".
         */
        if ($ttl === 0) {
            return -1;
        }

        /*
         * PSR-16 only accepts relative timestamps, whereas KeyValueStore
         * accepts both relative & absolute, depending on what the timestamp
         * is. We'll convert all timestamps > 30 days into absolute
         * timestamps; the others can remain relative, as KeyValueStore will
         * already treat those values as such.
         * @see https://github.com/dragoonis/psr-simplecache/issues/3
         */
        if ($ttl > 30 * 24 * 60 * 60) {
            return $ttl + time();
        }

        return $ttl;
    }
}
