<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Memcached adapter. Basically just a wrapper over \Memcached, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Memcached implements KeyValueStore
{
    /**
     * @var Memcached
     */
    protected $client;

    /**
     * @param \Memcached $client
     */
    public function __construct(\Memcached $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        /*
         * Wouldn't it be awesome if I just didn't use the obvious method? :)
         *
         * I'm going to use getMulti() instead of get() because the latter is
         * flawed in earlier versions, where it was known to mess up some
         * operations that are followed by it (increment/decrement have been
         * reported, also seen it make CAS return result unreliable)
         * @see https://github.com/php-memcached-dev/php-memcached/issues/21
         */
        $values = $this->client->getMulti(array($key), $token);

        if (!isset($values[$key])) {
            $token = null;

            return false;
        }

        $token = $token[$key];

        return $values[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $return = $this->client->getMulti($keys, $tokens);

        // HHVMs getMulti() returns null instead of empty array for no results,
        // so normalize that
        return $return ?: array();
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->client->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $success = $this->client->setMulti($items, $expire);

        return array_fill_keys(array_keys($items), $success);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->client->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        if (!method_exists($this->client, 'deleteMulti')) {
            /*
             * HHVM doesn't support deleteMulti, so I'll hack around it by
             * setting all items expired.
             * I could also delete() all items, but that would probably take
             * more network requests (this version always takes 2)
             *
             * @see http://docs.hhvm.com/manual/en/memcached.deletemulti.php
             */
            $values = $this->getMulti($keys);
            $this->client->setMulti(array_fill_keys(array_keys($values), ''), time() - 1);

            $return = array();
            foreach ($keys as $key) {
                $return[$key] = array_key_exists($key, $values);
            }

            return $return;
        }

        $result = (array) $this->client->deleteMulti($keys);

        /*
         * Contrary to docs (http://php.net/manual/en/memcached.deletemulti.php)
         * deleteMulti returns an array of [key => true] (for successfully
         * deleted values) and [key => error code] (for failures)
         * Pretty good because I want an array of true/false, so I'll just have
         * to replace the error codes by falses.
         */
        foreach ($result as $key => $status) {
            $result[$key] = $status === true;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        return $this->client->add($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        return $this->client->replace($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        if (!is_float($token)) {
            return false;
        }

        return $this->client->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        /*
         * Not doing \Memcached::increment because that one:
         * * needs \Memcached::OPT_BINARY_PROTOCOL == true
         * * is prone to errors after a flush ("merges" with pruned data) in at
         *   least some particular versions of Memcached
         */
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

        /*
         * Not doing \Memcached::decrement for the reasons described in:
         * @see increment()
         */
        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        /*
         * Since \Memcached has no reliable touch(), we might as well take an
         * easy approach where we can. If TTL is expired already, just delete
         * the key - this only needs 1 request.
         */
        if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
            return $this->delete($key);
        }

        /*
         * HHVM doesn't support touch.
         * @see http://docs.hhvm.com/manual/en/memcached.touch.php
         *
         * PHP does, but only with \Memcached::OPT_BINARY_PROTOCOL == true,
         * and even then, it appears to be buggy on particular versions of
         * Memcached.
         *
         * I'll just work around it!
         */
        $value = $this->get($key, $token);

        return $this->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->client->flush();
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
        $value = $this->get($key, $token);
        if ($value === false) {
            $this->set($key, $initial, $expire);

            return $initial;
        }

        if (!is_numeric($value) || $value < 0) {
            return false;
        }

        $value += $offset;
        // value can never be lower than 0
        $value = max(0, $value);
        $success = $this->client->cas($token, $key, $value, $expire);

        return $success ? $value : false;
    }
}
