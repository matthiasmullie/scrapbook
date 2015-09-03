<?php

namespace MatthiasMullie\Scrapbook\Buffered;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This class will serve as a local buffer to the real cache: anything read from
 * & written to the real cache will be stored in memory, so if any of those keys
 * is again requested in the same request, we can just grab it from memory
 * instead of having to get it over the wire.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class BufferedStore implements KeyValueStore
{
    /**
     * The real cache we're buffering data for.
     *
     * @var KeyValueStore
     */
    protected $cache;

    /**
     * Local in-memory storage, for the data we've already requested from
     * or written to the real cache.
     *
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
     * @param KeyValueStore $cache
     */
    public function __construct(KeyValueStore $cache)
    {
        $this->cache = $cache;
        $this->local = new Buffer();
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $value = $this->local->get($key);

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

            // store the value we just retrieved in local cache to prevent
            // follow-up lookups
            $this->local->set($key, $value);
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

            // store the value we just retrieved in local cache to prevent
            // follow-up lookups
            $this->local->setMulti($missing);
        }

        // any tokens we get will be unreliable, so generate some replacements
        // (more elaborate explanation in get()
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
        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), $key);

        // store the value in memory, so that when we ask for it again later in
        // this same request, we get the value we just set
        return $success && $this->local->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $cache = $this->cache;

        /*
         * We'll use the return value of all buffered writes to check if they
         * should be "rolled back" (which means deleting the keys to prevent
         * corruption).
         *
         * Always return 1 single true: commit() expects a single bool, not a
         * per-key array of success bools
         *
         * @param mixed[] $items
         * @param int $expire
         * @return bool
         */
        $setMulti = function ($items, $expire = 0) use ($cache) {
            $success = $cache->setMulti($items, $expire);

            return !in_array(false, $success);
        };

        $success = $this->defer($setMulti, func_get_args(), array_keys($items));
        $success = array_fill_keys(array_keys($items), $success);

        // store the values in memory, so that when we ask for it again later in
        // this same request, we get the value we just set
        return $success + $this->local->setMulti($items, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $cache = $this->cache;

        /*
         * We'll use the return value of all buffered writes to check if they
         * should be "rolled back" (which means deleting the keys to prevent
         * corruption).
         *
         * delete() can return false if the delete was issued on a non-existing
         * key. That is no corruption of data, though (the requested action
         * actually succeeded: the key is gone). Instead, make this callback
         * always return true, regardless of whether or not the key existed.
         *
         * @param string $key
         * @return bool
         */
        $delete = function ($key) use ($cache) {
            $cache->delete($key);

            return true;
        };

        $success = $this->defer($delete, func_get_args(), $key);

        // check the current value to see if it currently exists, so we can
        // properly return true/false as would be expected from KeyValueStore
        $value = $this->get($key);

        /*
         * To make sure that subsequent get() calls for this key don't return
         * a value (it's supposed to be deleted), we'll make it expired in our
         * temporary bag (as opposed to deleting it from out bag, in which case
         * we'd fall back to fetching it from real store, where the transaction
         * might not yet be committed)
         */
        $this->local->touch($key, -1);

        return $success && $value !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        $cache = $this->cache;

        /*
         * We'll use the return value of all buffered writes to check if they
         * should be "rolled back" (which means deleting the keys to prevent
         * corruption).
         *
         * Always return 1 single true: commit() expects a single bool, not a
         * per-key array of success bools (+ see comment in delete() about there
         * not being any data corruption)
         *
         * @param string[] $keys
         * @return bool
         */
        $deleteMulti = function ($keys) use ($cache) {
            $cache->deleteMulti($keys);

            return true;
        };

        $success = $this->defer($deleteMulti, func_get_args(), $keys);
        if ($success === false) {
            return array_fill_keys($keys, false);
        }

        // check the current values to see if they currently exists, so we can
        // properly return true/false as would be expected from KeyValueStore
        $items = $this->getMulti($keys);
        $success = array();
        foreach ($keys as $key) {
            $success[$key] = array_key_exists($key, $items);
        }

        // mark all as expired in local cache (see comment in delete())
        foreach ($keys as $key) {
            $this->local->touch($key, -1);
        }

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

        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), $key);

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        return $success && $this->local->set($key, $value, $expire);
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

        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), $key);

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        return $success && $this->local->set($key, $value, $expire);
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
     * Should a deferred CAS fail, however, we'll delete the key in cache
     * since it's no longer reliable.
     *
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $cache = $this->cache;
        $originalValue = isset($this->tokens[$token]) ? $this->tokens[$token] : null;

        /*
         * @param mixed $token
         * @param string $key
         * @param mixed $value
         * @param int $expire
         * @return bool
         */
        $cas = function ($token, $key, $value, $expire = 0) use ($cache, $originalValue) {
            // check if given (local) CAS token was known
            if ($originalValue === null) {
                return false;
            }

            // fetch data from real cache, getting new valid CAS token
            $current = $cache->get($key, $token);

            // check if the value we just read from real cache is still the same
            // as the one we saved when doing the original fetch
            if (serialize($current) === $originalValue) {
                // everything still checked out, CAS the value for real now
                return $cache->cas($token, $key, $value, $expire);
            }

            return false;
        };

        // CAS value to local cache/memory
        $success = false;
        if (serialize($this->get($key)) === $originalValue) {
            $success = $this->local->set($key, $value, $expire);
        }

        // only schedule the CAS to be performed on real cache if it was OK on
        // local cache
        if ($success) {
            $success = $this->defer($cas, func_get_args(), $key);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
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

        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), $key);
        if ($success === false) {
            return false;
        }

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        $value = max(0, $value + $offset);
        $success = $this->local->set($key, $value, $expire);

        return $success ? $value : false;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
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

        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), $key);
        if ($success === false) {
            return false;
        }

        // store the value in memory, so that when we ask for it again later
        // in this same request, we get the value we just set
        $value = max(0, $value - $offset);
        $success = $this->local->set($key, $value, $expire);

        return $success ? $value : false;
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), $key);

        // grab existing value (from real cache or memory) and re-save (to
        // memory) with updated expiration time
        $value = $this->get($key);

        return $success && $this->local->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        // clear all buffered writes, flush wipes them out anyway
        $this->clearLocal();

        $success = $this->defer(array($this->cache, __FUNCTION__), func_get_args(), array());

        return $success;
    }

    /**
     * @param callable        $callback
     * @param array           $arguments
     * @param string|string[] $key       Key(s) being written to
     *
     * @return bool
     */
    protected function defer($callback, $arguments, $key)
    {
        // keys can be either 1 single string or array of multiple keys
        $keys = (array) $key;

        $success = call_user_func_array($callback, $arguments);

        // if operation on real cache failed, clear whatever is in local cache
        if ($success === false) {
            $this->local->deleteMulti($keys);
        }

        return $success;
    }

    /**
     * Clears all data stored in memory.
     */
    protected function clearLocal()
    {
        $this->local->flush();
        $this->tokens = array();
    }
}
