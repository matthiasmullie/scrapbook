<?php

namespace MatthiasMullie\Scrapbook\Scale;

use MatthiasMullie\Scrapbook\Exception\InvalidKey;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Cache is usually used to reduce performing a complex operation. In case of a
 * cache miss, that operation is executed & the result is stored.
 *
 * A cache stampede happens when there are a lot of requests for data that is
 * not currently in cache. Examples could be:
 * * cache expires for something that is often under very heavy load
 * * sudden unexpected high load on something that is likely to not be in cache
 * In those cases, this huge amount of requests for data that is not at that
 * time in cache, causes that expensive operation to be executed a lot of times,
 * all at once.
 *
 * This class is designed to counteract that: if a value can't be found in cache
 * we'll write something else to cache for a short period of time, to indicate
 * that another process has already requested this same key (and is probably
 * already performing that complex operation that will result in the key being
 * filled)
 *
 * All of the follow-up requests (that find that the "stampede indicator" has
 * already been set) will just wait (usleep): instead of crippling the servers
 * by all having to execute the same operation, these processes will just idle
 * to give the first process the chance to fill in the cache. Periodically,
 * these processes will poll the cache to see if the value has already been
 * stored in the meantime.
 *
 * The stampede protection will only be temporary, for $sla milliseconds. We
 * need to limit it because the first process (tasked with filling the cache
 * after executing the expensive operation) may fail/crash/... If the expensive
 * operation fails to conclude in < $sla milliseconds.
 * This class guarantees that the stampede will hold off for $sla amount of time
 * but after that, all follow-up requests will go through without cached values
 * and cause a stampede after all, if the initial process fails to complete
 * within that time.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class StampedeProtector implements KeyValueStore
{
    /**
     * @var KeyValueStore
     */
    protected $cache = array();

    /**
     * Amount of time, in milliseconds, this class guarantees protection.
     *
     * @var int
     */
    protected $sla = 1000;

    /**
     * Amount of times every process will poll within $sla time.
     *
     * @var int
     */
    protected $attempts = 10;

    /**
     * @param KeyValueStore $cache The real cache we'll buffer for
     * @param int           $sla   Stampede protection time, in milliseconds
     */
    public function __construct(KeyValueStore $cache, $sla = 1000)
    {
        $this->cache = $cache;
        $this->sla = $sla;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $values = $this->getMulti(array($key), $tokens);
        $token = isset($tokens[$key]) ? $tokens[$key] : null;

        return isset($values[$key]) ? $values[$key] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        // fetch both requested keys + stampede protection indicators at once
        $stampedeKeys = array_combine($keys, array_map(array($this, 'stampedeKey'), $keys));
        $values = $this->cache->getMulti(array_merge($keys, $stampedeKeys), $tokens);

        // figure out which of the requested keys are protected, and which need
        // protection (=currently empty & not yet protected)
        $protected = array_keys(array_intersect($stampedeKeys, array_keys($values)));
        $protect = array_diff($keys, array_keys($values), $protected);

        // protect keys that we couldn't find, and remove them from the list of
        // keys we want results from, because we'll keep fetching empty keys
        // (that are currently protected)
        $done = $this->protect($protect);
        $keys = array_diff($keys, $done);

        // we may have failed to protect some keys after all (race condition
        // with another process), in which case we also have to keep polling
        // those keys (which the other process is likely working on already)
        $protected += array_diff($protect, $done);

        // we over-fetched (to include stampede indicators), now limit the
        // results to only the keys we requested
        $results = array_intersect_key($values, array_flip($keys));
        $tokens = array_intersect_key($tokens, $results);

        // we may not have been able to retrieve all keys yet: some may have
        // been "protected" (and are being regenerated in another process) in
        // which case we'll retry a couple of times, hoping the other process
        // stores the new value in the meantime
        $attempts = $this->attempts;
        while (--$attempts > 0 && !empty($protected) && $this->sleep()) {
            $values = $this->cache->getMulti($protected, $tokens2);

            $results += array_intersect_key($values, array_flip($keys));
            $tokens += array_intersect_key($tokens2, array_flip($keys));

            // don't keep polling for values we just fetched...
            $protected = array_diff($protected, array_keys($values));
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->cache->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        return $this->cache->setMulti($items, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->cache->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        return $this->cache->deleteMulti($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        return $this->cache->add($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        return $this->cache->replace($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        return $this->cache->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        return $this->cache->increment($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        return $this->cache->decrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        return $this->cache->touch($key, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->cache->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        $collection = $this->cache->getCollection($name);

        return new static($collection);
    }

    /**
     * As soon as a key turns up empty (doesn't yet exist in cache), we'll
     * "protect" it for some time. This will be done by writing to a key similar
     * to the original key name. If this key is present (which it will only be
     * for a short amount of time) we'll know it's protected.
     *
     * @param array $keys
     *
     * @return string[] Array of keys that were successfully protected
     */
    protected function protect(array $keys)
    {
        if (empty($keys)) {
            return array();
        }

        $success = array();
        foreach ($keys as $key) {
            /*
             * Key is add()ed because there may be multiple concurrent processes
             * that are both in the process of protecting - first one to add()
             * wins (and those are returned by the function, so those that are
             * failed to protect can be considered protected)
             */
            $success[$key] = $this->cache->add($this->stampedeKey($key), '', $this->sla);
        }

        return array_keys(array_filter($success));
    }

    /**
     * When waiting for stampede-protected keys, we'll just sleep, not using
     * much resources.
     *
     * @return bool
     */
    protected function sleep()
    {
        $break = $this->sla / $this->attempts;
        usleep(1000 * $break);

        return true;
    }

    /**
     * To figure out if something has recently been requested already (and is
     * likely in the process of being recalculated), we'll temporarily write to
     * another key, so follow-up requests know another process is likely already
     * re-processing the value.
     *
     * @param string $key
     *
     * @return string
     *
     * @throws InvalidKey
     */
    protected function stampedeKey($key)
    {
        $suffix = '.stampede';

        if (substr($key, -strlen($suffix)) === $suffix) {
            throw new InvalidKey(
                "Invalid key: $key. Keys with suffix '$suffix' are reserved."
            );
        }

        return $key.$suffix;
    }
}
