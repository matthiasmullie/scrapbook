<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Buffered\Utils;

use MatthiasMullie\Scrapbook\Adapters\Collections\MemoryStore as BufferCollection;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a helper class for BufferedStore & TransactionalStore, which buffer
 * real cache requests in memory.
 *
 * This class accepts 2 caches: a KeyValueStore object (the real cache) and a
 * Buffer instance (to read data from as long as it hasn't been committed)
 *
 * Every write action will first store the data in the Buffer instance, and
 * then pas update along to $defer.
 * Once commit() is called, $defer will execute all these updates against the
 * real cache. All deferred writes that fail to apply will cause that cache key
 * to be deleted, to ensure cache consistency.
 * Until commit() is called, all data is read from the temporary Buffer instance.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Transaction implements KeyValueStore
{
    protected KeyValueStore $cache;

    protected Buffer|BufferCollection $local;

    /**
     * We'll return stub CAS tokens in order to reliably replay the CAS actions
     * to the real cache. This will hold a map of stub token => value, used to
     * verify when we do the actual CAS.
     *
     * @see cas()
     */
    protected array $tokens = [];

    /**
     * Deferred updates to be committed to real cache.
     */
    protected Defer $defer;

    /**
     * Suspend reads from real cache. This is used when a flush is issued but it
     * has not yet been committed. In that case, we don't want to fall back to
     * real cache values, because they're about to be flushed.
     */
    protected bool $suspend = false;

    /**
     * @var Transaction[]
     */
    protected array $collections = [];

    public function __construct(Buffer|BufferCollection $local, KeyValueStore $cache)
    {
        $this->cache = $cache;

        // (uncommitted) writes must never be evicted (even if that means
        // crashing because we run out of memory)
        $this->local = $local;

        $this->defer = new Defer($this->cache);
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        $value = $this->local->get($key, $token);

        // short-circuit reading from real cache if we have an uncommitted flush
        if ($this->suspend && $token === null) {
            // flush hasn't been committed yet, don't read from real cache!
            return false;
        }

        if ($value === false) {
            if ($this->local->expired($key)) {
                /*
                 * Item used to exist in local cache, but is now expired. This
                 * is used when values are to be deleted: we don't want to reach
                 * out to real storage because that would respond with the not-
                 * yet-deleted value.
                 */

                return false;
            }

            // unknown in local cache = fetch from source cache
            $value = $this->cache->get($key, $token);
        }

        // no value = quit early, don't generate a useless token
        if ($value === false) {
            return false;
        }

        /*
         * $token will be unreliable to the deferred updates so generate
         * a custom one and keep the associated value around.
         * Read more details in PHPDoc for function cas().
         * uniqid is ok here. Doesn't really have to be unique across
         * servers, just has to be unique every time it's called in this
         * one particular request - which it is.
         */
        $token = uniqid('', false);
        $this->tokens[$token] = serialize($value);

        return $value;
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        // retrieve all that we can from local cache
        $values = $this->local->getMulti($keys);
        $tokens = [];

        // short-circuit reading from real cache if we have an uncommitted flush
        if (!$this->suspend) {
            // figure out which missing key we need to get from real cache
            $keys = array_diff($keys, array_keys($values));
            foreach ($keys as $i => $key) {
                // don't reach out to real cache for keys that are about to be gone
                if ($this->local->expired($key)) {
                    unset($keys[$i]);
                }
            }

            // fetch missing values from real cache
            if ($keys) {
                $missing = $this->cache->getMulti($keys);
                $values += $missing;
            }
        }

        // any tokens we get will be unreliable, so generate some replacements
        // (more elaborate explanation in get())
        foreach ($values as $key => $value) {
            $token = uniqid('', false);
            $tokens[$key] = $token;
            $this->tokens[$token] = serialize($value);
        }

        return $values;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        // store the value in memory, so that when we ask for it again later in
        // this same request, we get the value we just set
        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->set($key, $value, $expire);

        return true;
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        // store the values in memory, so that when we ask for it again later in
        // this same request, we get the value we just set
        $success = $this->local->setMulti($items, $expire);

        // only attempt to store those that we've set successfully to local
        $successful = array_intersect_key($items, $success);
        if (!empty($successful)) {
            $this->defer->setMulti($successful, $expire);
        }

        return $success;
    }

    public function delete(string $key): bool
    {
        // check the current value to see if it currently exists, so we can
        // properly return true/false as would be expected from KeyValueStore
        $value = $this->get($key);
        if ($value === false) {
            return false;
        }

        /*
         * To make sure that subsequent get() calls for this key don't return
         * a value (it's supposed to be deleted), we'll make it expired in our
         * temporary bag (as opposed to deleting it from out bag, in which case
         * we'd fall back to fetching it from real store, where the transaction
         * might not yet be committed)
         */
        $this->local->set($key, true, -1);

        $this->defer->delete($key);

        return true;
    }

    public function deleteMulti(array $keys): array
    {
        // check the current values to see if they currently exists, so we can
        // properly return true/false as would be expected from KeyValueStore
        $items = $this->getMulti($keys);
        $success = [];
        foreach ($keys as $key) {
            $success[$key] = array_key_exists($key, $items);
        }

        // only attempt to store those that we've deleted successfully to local
        $values = array_intersect_key($success, array_flip($keys));
        if (empty($values)) {
            return [];
        }

        // mark all as expired in local cache (see comment in delete())
        $this->local->setMulti($values, -1);

        $this->defer->deleteMulti(array_keys($values));

        return $success;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        // before adding, make sure the value doesn't yet exist (in real cache,
        // nor in memory)
        if ($this->get($key) !== false) {
            return false;
        }

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->add($key, $value, $expire);

        return true;
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        // before replacing, make sure the value actually exists (in real cache,
        // or already created in memory)
        if ($this->get($key) === false) {
            return false;
        }

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->replace($key, $value, $expire);

        return true;
    }

    /**
     * Since our CAS is deferred, the CAS token we got from our original
     * get() will likely not be valid by the time we want to store it to
     * the real cache. Imagine this scenario:
     * * a value is fetched from (real) cache
     * * an new value key is CAS'ed (into temp cache - real CAS is deferred)
     * * this key's value is fetched again (this time from temp cache)
     * * and a new value is CAS'ed again (into temp cache...).
     *
     * In this scenario, when we finally want to replay the write actions
     * onto the real cache, the first 3 actions would likely work fine.
     * The last (second CAS) however would not, since it never got a real
     * updated $token from the real cache.
     *
     * To work around this problem, all get() calls will return a unique
     * CAS token and store the value-at-that-time associated with that
     * token. All we have to do when we want to write the data to real cache
     * is, right before was CAS for real, get the value & (real) cas token
     * from storage & compare that value to the one we had stored. If that
     * checks out, we can safely resume the CAS with the real token we just
     * received.
     *
     * {@inheritdoc}
     */
    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        $originalValue = $this->tokens[$token] ?? null;

        // value is no longer the same as what we used for token
        if (serialize($this->get($key)) !== $originalValue) {
            return false;
        }

        // "CAS" value to local cache/memory
        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        // only schedule the CAS to be performed on real cache if it was OK on
        // local cache
        $this->defer->cas($originalValue, $key, $value, $expire);

        return true;
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        // get existing value (from real cache or memory) so we know what to
        // increment in memory (where we may not have anything yet, so we should
        // adjust our initial value to what's already in real cache)
        $value = $this->get($key);
        if ($value === false) {
            $value = $initial - $offset;
        }

        if (!is_numeric($value) || !is_numeric($offset)) {
            return false;
        }

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        $value = max(0, $value + $offset);
        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->increment($key, $offset, $initial, $expire);

        return $value;
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        // get existing value (from real cache or memory) so we know what to
        // increment in memory (where we may not have anything yet, so we should
        // adjust our initial value to what's already in real cache)
        $value = $this->get($key);
        if ($value === false) {
            $value = $initial + $offset;
        }

        if (!is_numeric($value) || !is_numeric($offset)) {
            return false;
        }

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        $value = max(0, $value - $offset);
        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->decrement($key, $offset, $initial, $expire);

        return $value;
    }

    public function touch(string $key, int $expire): bool
    {
        // grab existing value (from real cache or memory) and re-save (to
        // memory) with updated expiration time
        $value = $this->get($key);
        if ($value === false) {
            return false;
        }

        $success = $this->local->set($key, true, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->touch($key, $expire);

        return true;
    }

    public function flush(): bool
    {
        foreach ($this->collections as $collection) {
            $collection->flush();
        }

        $success = $this->local->flush();
        if ($success === false) {
            return false;
        }

        // clear all buffered writes, flush wipes them out anyway
        $this->clear();

        // make sure that reads, from now on until commit, don't read from cache
        $this->suspend = true;

        $this->defer->flush();

        return true;
    }

    public function getCollection(string $name): KeyValueStore
    {
        if (!isset($this->collections[$name])) {
            /** @var BufferCollection $local */
            $local = $this->local->getCollection($name);
            $cache = $this->cache->getCollection($name);
            $this->collections[$name] = new static($local, $cache);
        }

        return $this->collections[$name];
    }

    /**
     * Commits all deferred updates to real cache.
     * that had already been written to will be deleted.
     */
    public function commit(): bool
    {
        $this->clear();

        return $this->defer->commit();
    }

    /**
     * Roll back all scheduled changes.
     */
    public function rollback(): bool
    {
        $this->clear();
        $this->defer->clear();

        return true;
    }

    /**
     * Clears all transaction-related data stored in memory.
     */
    protected function clear(): void
    {
        $this->tokens = [];
        $this->suspend = false;
    }
}
