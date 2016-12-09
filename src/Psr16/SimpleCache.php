<?php

namespace MatthiasMullie\Scrapbook\Psr16;

use DateInterval;
use DateTime;
use MatthiasMullie\Scrapbook\KeyValueStore;
use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class SimpleCache implements CacheInterface
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
    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.serialize($key).'. Must be string.'
            );
        }

        // KeyValueStore::get returns false for cache misses (which could also
        // be confused for a `false` value), so we'll check existence with getMulti
        $multi = $this->store->getMulti(array($key));

        return isset($multi[$key]) ? $multi[$key] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.serialize($key).'. Must be string.'
            );
        }

        $ttl = $this->ttl($ttl);

        return $this->store->set($key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.serialize($key).'. Must be string.'
            );
        }

        return $this->store->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return $this->store->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys);
        }

        if (!is_array($keys) || array_filter($keys, 'is_string') !== $keys) {
            throw new InvalidArgumentException(
                'Invalid keys: '.serialize($keys).'. Must be array of strings.'
            );
        }

        $results = $this->store->getMulti($keys);

        // KeyValueStore omits values that are not in cache, while PSR-16 will
        // have them with a default value
        $nulls = array_fill_keys($keys, $default);
        $results = array_merge($nulls, $results);

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple($values, $ttl = null)
    {
        if ($values instanceof Traversable) {
            $values = iterator_to_array($values);
        }

        $keys = array_keys($values);
        if (!is_array($keys) || array_filter($keys, 'is_string') !== $keys) {
            throw new InvalidArgumentException(
                'Invalid keys: '.serialize($keys).'. Must be array of strings.'
            );
        }

        $ttl = $this->ttl($ttl);
        $success = $this->store->setMulti($values, $ttl);

        return !in_array(false, $success);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple($keys)
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys);
        }

        if (!is_array($keys) || array_filter($keys, 'is_string') !== $keys) {
            throw new InvalidArgumentException(
                'Invalid keys: '.serialize($keys).'. Must be array of strings.'
            );
        }

        $success = $this->store->deleteMulti($keys);

        return !in_array(false, $success);
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(
                'Invalid key: '.serialize($key).'. Must be string.'
            );
        }

        // KeyValueStore::get returns false for cache misses (which could also
        // be confused for a `false` value), so we'll check existence with getMulti
        $multi = $this->store->getMulti(array($key));

        return isset($multi[$key]);
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
            /*
             * PSR-16 only accepts relative timestamps, whereas KeyValueStore
             * accepts both relative & absolute, depending on what the timestamp
             * is. We'll have to convert all ttls here to absolute, to make sure
             * KeyValueStore doesn't get confused.
             * @see https://github.com/dragoonis/psr-simplecache/issues/3
             */
            if ($ttl < 30 * 24 * 60 * 60) {
                return $ttl + time();
            }

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
