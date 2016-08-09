<?php

namespace MatthiasMullie\Scrapbook\Psr16;

use DateInterval;
use DateTime;
use MatthiasMullie\Scrapbook\KeyValueStore;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\CounterInterface;
use Traversable;

/**
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license LICENSE MIT
 */
class SimpleCache implements CacheInterface, CounterInterface
{
    /**
     * @var KeyValueStore
     */
    protected $store;

    /**
     * @param KeyValueStore $store
     */
    public function __construct(KeyValueStore $store)
    {
        $this->store = $store;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        // KeyValueStore::get returns false for cache misses (which could also
        // be confused for a `false` value), so we'll check existence with getMulti
        $multi = $this->store->getMulti(array($key));

        return isset($multi[$key]) ? $multi[$key] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        $ttl = $this->ttl($ttl);

        return $this->store->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $this->store->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->store->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys)
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys);
        }

        $results = $this->store->getMulti($keys);

        // KeyValueStore omits values that are not in cache, while PSR-16 will
        // have them with null as value
        $nulls = array_fill_keys($keys, null);
        $results = array_merge($nulls, $results);

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($items, $ttl = null)
    {
        $ttl = $this->ttl($ttl);
        $success = $this->store->setMulti($items, $ttl);

        return !in_array(false, $success);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        $this->store->deleteMulti($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($key)
    {
        // KeyValueStore::get returns false for cache misses (which could also
        // be confused for a `false` value), so we'll check existence with getMulti
        $multi = $this->store->getMulti(array($key));

        return isset($multi[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $step = 1)
    {
        return $this->store->increment($key, $step, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $step = 1)
    {
        return $this->store->decrement($key, $step, 1);
    }

    /**
     * Accepts all TTL inputs valid in PSR-16 (null|int|DateInterval) and
     * converts them into TTL for KeyValueStore (int).
     *
     * @param null|int|DateInterval $ttl
     *
     * @return int
     *
     * @throws \TypeError
     */
    protected function ttl($ttl)
    {
        if ($ttl === null) {
            return 0;
        } elseif (is_int($ttl)) {
            return $ttl;
        } elseif ($ttl instanceof DateInterval) {
            // convert DateInterval to integer by adding it to a 0 DateTime
            $datetime = new DateTime();
            $datetime->setTimestamp(0);
            $datetime->add($ttl);

            return time() + (int) $datetime->format('U');
        }

        $error = 'Invalid time: '.serialize($ttl).'. Must be integer or '.
            'instance of DateInterval.';

        if (class_exists('\TypeError')) {
            throw new \TypeError($error);
        }
        trigger_error($error, E_USER_ERROR);

        return 0;
    }
}
