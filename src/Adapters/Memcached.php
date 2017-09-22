<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\Adapters\Collections\Memcached as Collection;
use MatthiasMullie\Scrapbook\Exception\InvalidKey;
use MatthiasMullie\Scrapbook\Exception\OperationFailed;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Memcached adapter. Basically just a wrapper over \Memcached, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Memcached implements KeyValueStore
{
    /**
     * @var \Memcached
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
        /**
         * Wouldn't it be awesome if I just used the obvious method?
         *
         * I'm going to use getMulti() instead of get() because the latter is
         * flawed in earlier versions, where it was known to mess up some
         * operations that are followed by it (increment/decrement have been
         * reported, also seen it make CAS return result unreliable)
         *
         * @see https://github.com/php-memcached-dev/php-memcached/issues/21
         */
        $values = $this->getMulti(array($key), $tokens);

        if (!isset($values[$key])) {
            $token = null;

            return false;
        }

        $token = $tokens[$key];

        return $values[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $tokens = array();
        if (empty($keys)) {
            return array();
        }

        $keys = array_map(array($this, 'encode'), $keys);

        if (defined('\Memcached::GET_EXTENDED')) {
            $return = $this->client->getMulti($keys, \Memcached::GET_EXTENDED);
            $this->throwExceptionOnClientCallFailure($return);
            foreach ($return as $key => $value) {
                // once PHP<5.5 support is dropped, just use array_column
                $tokens[$key] = $value['cas'];
                $return[$key] = $value['value'];
            }
        } else {
            $return = $this->client->getMulti($keys, $tokens);
            $this->throwExceptionOnClientCallFailure($return);
        }

        $keys = array_map(array($this, 'decode'), array_keys($return));
        $return = array_combine($keys, $return);

        // HHVMs getMulti() returns null instead of empty array for no results,
        // so normalize that
        $tokens = $tokens ?: array();
        $tokens = array_combine($keys, $tokens);

        return $return ?: array();
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        // Memcached seems to not timely purge items the way it should when
        // storing it with an expired timestamp
        if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
            $this->delete($key);

            return true;
        }

        $key = $this->encode($key);

        return $this->client->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        if (empty($items)) {
            return array();
        }

        // Memcached seems to not timely purge items the way it should when
        // storing it with an expired timestamp
        if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
            $keys = array_keys($items);
            $this->deleteMulti($keys);

            return array_fill_keys($keys, true);
        }

        if (defined('HHVM_VERSION')) {
            $nums = array_filter(array_keys($items), 'is_numeric');
            if (!empty($nums)) {
                return $this->setMultiNumericItemsForHHVM($items, $nums, $expire);
            }
        }

        $keys = array_map(array($this, 'encode'), array_keys($items));
        $items = array_combine($keys, $items);
        $success = $this->client->setMulti($items, $expire);
        $keys = array_map(array($this, 'decode'), array_keys($items));

        return array_fill_keys($keys, $success);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $key = $this->encode($key);

        return $this->client->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        if (empty($keys)) {
            return array();
        }

        if (!method_exists($this->client, 'deleteMulti')) {
            /**
             * HHVM didn't always support deleteMulti, so I'll hack around it by
             * setting all items expired.
             * I could also delete() all items one by one, but that would
             * probably take more network requests (this version always takes 2).
             *
             * @see http://docs.hhvm.com/manual/en/memcached.deletemulti.php
             */
            $values = $this->getMulti($keys);

            $keys = array_map(array($this, 'encode'), array_keys($values));
            $this->client->setMulti(array_fill_keys($keys, ''), time() - 1);

            $return = array();
            foreach ($keys as $key) {
                $key = $this->decode($key);
                $return[$key] = array_key_exists($key, $values);
            }

            return $return;
        }

        $keys = array_map(array($this, 'encode'), $keys);
        $result = (array) $this->client->deleteMulti($keys);
        $keys = array_map(array($this, 'decode'), array_keys($result));
        $result = array_combine($keys, $result);

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
        $key = $this->encode($key);

        return $this->client->add($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $key = $this->encode($key);

        return $this->client->replace($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        if (!is_float($token) && !is_int($token)) {
            return false;
        }

        $key = $this->encode($key);

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

        /**
         * HHVM doesn't support touch.
         *
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
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        return new Collection($this, $name);
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
            $success = $this->add($key, $initial, $expire);

            return $success ? $initial : false;
        }

        if (!is_numeric($value) || $value < 0) {
            return false;
        }

        $value += $offset;
        // value can never be lower than 0
        $value = max(0, $value);
        $key = $this->encode($key);
        $success = $this->client->cas($token, $key, $value, $expire);

        return $success ? $value : false;
    }

    /**
     * Encode a key for use on the wire inside the memcached protocol.
     *
     * We encode spaces and line breaks to avoid protocol errors. We encode
     * the other control characters for compatibility with libmemcached
     * verify_key. We leave other punctuation alone, to maximise backwards
     * compatibility.
     *
     * @see https://github.com/wikimedia/mediawiki/commit/be76d869#diff-75b7c03970b5e43de95ff95f5faa6ef1R100
     * @see https://github.com/wikimedia/mediawiki/blob/master/includes/libs/objectcache/MemcachedBagOStuff.php#L116
     *
     * @param string $key
     *
     * @return string
     *
     * @throws InvalidKey
     */
    protected function encode($key)
    {
        $regex = '/[^\x21\x22\x24\x26-\x39\x3b-\x7e]+/';
        $key = preg_replace_callback($regex, function ($match) {
            return rawurlencode($match[0]);
        }, $key);

        if (strlen($key) > 255) {
            throw new InvalidKey(
                "Invalid key: $key. Encoded Memcached keys can not exceed 255 chars."
            );
        }

        return $key;
    }

    /**
     * Decode a key encoded with encode().
     *
     * @param string $key
     *
     * @return string
     */
    protected function decode($key)
    {
        // matches %20, %7F, ... but not %21, %22, ...
        // (=the encoded versions for those encoded in encode)
        $regex = '/%(?!2[1246789]|3[0-9]|3[B-F]|[4-6][0-9A-F]|5[0-9A-E])[0-9A-Z]{2}/i';

        return preg_replace_callback($regex, function ($match) {
            return rawurldecode($match[0]);
        }, $key);
    }

    /**
     * Numerical strings turn into integers when used as array keys, and
     * HHVM (used to) reject(s) such cache keys.
     *
     * @see https://github.com/facebook/hhvm/pull/7654
     *
     * @param array $items
     * @param array $nums
     * @param int   $expire
     *
     * @return array
     */
    protected function setMultiNumericItemsForHHVM(array $items, array $nums, $expire = 0)
    {
        $success = [];
        $nums = array_intersect_key($items, array_fill_keys($nums, null));
        foreach ($nums as $k => $v) {
            $success[$k] = $this->set((string) $k, $v, $expire);
        }

        $remaining = array_diff_key($items, $nums);
        if ($remaining) {
            $success += $this->setMulti($remaining, $expire);
        }

        return $success;
    }

    /**
     * Will throw an exception if the returned result from a Memcached call
     * indicates a failure in the operation.
     * The exception will contain debug information about the failure.
     *
     * @param mixed $result
     *
     * @throws OperationFailed
     */
    protected function throwExceptionOnClientCallFailure($result)
    {
        if ($result !== false) {
            return;
        }

        throw new OperationFailed(
            $this->client->getResultMessage(),
            $this->client->getResultCode()
        );
    }
}
