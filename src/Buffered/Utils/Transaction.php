<?php

namespace MatthiasMullie\Scrapbook\Buffered\Utils;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Adapters\Collections\MemoryStore as BufferCollection;

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
    /**
     * @var KeyValueStore
     */
    protected $cache;

    /**
     * @var Buffer
     */
    protected $local;

    /**
     * We'll return stub CAS tokens in order to reliably replay the CAS actions
     * to the real cache. This will hold a map of stub token => value, used to
     * verify when we do the actual CAS.
     *
     * @see cas()
     *
     * @var mixed[]
     */
    protected $tokens = array();

    /**
     * Deferred updates to be committed to real cache.
     *
     * @var Defer
     */
    protected $defer;

    /**
     * Suspend reads from real cache. This is used when a flush is issued but it
     * has not yet been committed. In that case, we don't want to fall back to
     * real cache values, because they're about to be flushed.
     *
     * @var bool
     */
    protected $suspend = false;

    /**
     * @var Transaction[]
     */
    protected $collections = array();

    /**
     * @param Buffer|BufferCollection $local
     * @param KeyValueStore           $cache
     */
    public function __construct(/* Buffer|BufferCollection */ $local, KeyValueStore $cache)
    {
        // can't do double typehint, so let's manually check the type
        if (!$local instanceof Buffer && !$local instanceof BufferCollection) {
            $error = 'Invalid class for $local: '.get_class($local);
            if (class_exists('\TypeError')) {
                throw new \TypeError($error);
            }
            trigger_error($error, E_USER_ERROR);
        }

        $this->cache = $cache;

        // (uncommitted) writes must never be evicted (even if that means
        // crashing because we run out of memory)
        $this->local = $local;

        $this->defer = new Defer($this->cache);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
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
        $token = uniqid();
        $this->tokens[$token] = serialize($value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        // retrieve all that we can from local cache
        $values = $this->local->getMulti($keys);
        $tokens = array();

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
            $token = uniqid();
            $tokens[$key] = $token;
            $this->tokens[$token] = serialize($value);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
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

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
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

    /**
     * {@inheritdoc}
     */
    public function delete($key)
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
        $this->local->set($key, $value, -1);

        $this->defer->delete($key);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        // check the current values to see if they currently exists, so we can
        // properly return true/false as would be expected from KeyValueStore
        $items = $this->getMulti($keys);
        $success = array();
        foreach ($keys as $key) {
            $success[$key] = array_key_exists($key, $items);
        }

        // only attempt to store those that we've deleted successfully to local
        $values = array_intersect_key($success, array_flip($keys));
        if (empty($values)) {
            return array();
        }

        // mark all as expired in local cache (see comment in delete())
        $this->local->setMulti($values, -1);

        $this->defer->deleteMulti(array_keys($values));

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
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

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
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
    public function cas($token, $key, $value, $expire = 0)
    {
        $originalValue = isset($this->tokens[$token]) ? $this->tokens[$token] : null;

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

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
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

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
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

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        // grab existing value (from real cache or memory) and re-save (to
        // memory) with updated expiration time
        $value = $this->get($key);
        if ($value === false) {
            return false;
        }

        $success = $this->local->set($key, $value, $expire);
        if ($success === false) {
            return false;
        }

        $this->defer->touch($key, $expire);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
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

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        if (!isset($this->collections[$name])) {
            $this->collections[$name] = new static(
                $this->local->getCollection($name),
                $this->cache->getCollection($name)
            );
        }

        return $this->collections[$name];
    }

    /**
     * Commits all deferred updates to real cache.
     * that had already been written to will be deleted.
     *
     * @return bool
     */
    public function commit()
    {
        $this->clear();

        return $this->defer->commit();
    }

    /**
     * Roll back all scheduled changes.
     *
     * @return bool
     */
    public function rollback()
    {
        $this->clear();
        $this->defer->clear();

        return true;
    }

    /**
     * Clears all transaction-related data stored in memory.
     */
    protected function clear()
    {
        $this->tokens = array();
        $this->suspend = false;
    }
}
