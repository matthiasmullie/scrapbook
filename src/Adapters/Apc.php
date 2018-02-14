<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use APCIterator;
use APCuIterator;
use MatthiasMullie\Scrapbook\Adapters\Collections\Apc as Collection;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * APC adapter. Basically just a wrapper over apc_* functions, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Apc implements KeyValueStore
{
    /**
     * APC does this crazy thing of only deleting expired data on every new
     * (page) request, not checking it when you actually retrieve the value
     * (which you may just have set in the same request)
     * Since it's totally possible to store values that expire in the same
     * request, we'll keep track of those expiration times here & filter them
     * out in case they did expire.
     *
     * @see http://stackoverflow.com/questions/11750223/apc-user-cache-entries-not-expiring
     *
     * @var array
     */
    protected $expires = array();

    public function __construct()
    {
        if (!function_exists('apcu_fetch') && !function_exists('apc_fetch')) {
            throw new Exception('ext-apc(u) is not installed.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        // check for values that were just stored in this request but have
        // actually expired by now
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            return false;
        }

        $value = $this->apcu_fetch($key, $success);
        if ($success === false) {
            $token = null;

            return false;
        }

        $token = serialize($value);

        return $value;
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

        // check for values that were just stored in this request but have
        // actually expired by now
        foreach ($keys as $i => $key) {
            if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
                unset($keys[$i]);
            }
        }

        $values = $this->apcu_fetch($keys);
        if ($values === false) {
            return array();
        }

        $tokens = array();
        foreach ($values as $key => $value) {
            $tokens[$key] = serialize($value);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $ttl = $this->ttl($expire);

        // negative TTLs don't always seem to properly treat the key as deleted
        if ($ttl < 0) {
            $this->delete($key);

            return true;
        }

        // lock required for CAS
        if (!$this->lock($key)) {
            return false;
        }

        $success = $this->apcu_store($key, $value, $ttl);
        $this->expire($key, $ttl);
        $this->unlock($key);

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        if (empty($items)) {
            return array();
        }

        $ttl = $this->ttl($expire);

        // negative TTLs don't always seem to properly treat the key as deleted
        if ($ttl < 0) {
            $this->deleteMulti(array_keys($items));

            return array_fill_keys(array_keys($items), true);
        }

        // attempt to get locks for all items
        $locked = $this->lock(array_keys($items));
        $locked = array_fill_keys($locked, null);
        $failed = array_diff_key($items, $locked);
        $items = array_intersect_key($items, $locked);

        if ($items) {
            // only write to those where lock was acquired
            $this->apcu_store($items, null, $ttl);
            $this->expire(array_keys($items), $ttl);
            $this->unlock(array_keys($items));
        }

        $return = array();
        foreach ($items as $key => $value) {
            $return[$key] = !array_key_exists($key, $failed);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        // lock required for CAS
        if (!$this->lock($key)) {
            return false;
        }

        $success = $this->apcu_delete($key);
        unset($this->expires[$key]);
        $this->unlock($key);

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        if (empty($keys)) {
            return array();
        }

        // attempt to get locks for all items
        $locked = $this->lock($keys);
        $failed = array_diff($keys, $locked);
        $keys = array_intersect($keys, $locked);

        // only delete those where lock was acquired
        if ($keys) {
            /**
             * Contrary to the docs, apc_delete also accepts an array of
             * multiple keys to be deleted. Docs for apcu_delete are ok in this
             * regard.
             * But both are flawed in terms of return value in this case: an
             * array with failed keys is returned.
             *
             * @see http://php.net/manual/en/function.apc-delete.php
             *
             * @var string[]
             */
            $result = $this->apcu_delete($keys);
            $failed = array_merge($failed, $result);
            $this->unlock($keys);
        }

        $return = array();
        foreach ($keys as $key) {
            $return[$key] = !in_array($key, $failed);
            unset($this->expires[$key]);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $ttl = $this->ttl($expire);

        // negative TTLs don't always seem to properly treat the key as deleted
        if ($ttl < 0) {
            // don't add - it's expired already; just check if it already
            // existed to return true/false as expected from add()
            return $this->get($key) === false;
        }

        // lock required for CAS
        if (!$this->lock($key)) {
            return false;
        }

        $success = $this->apcu_add($key, $value, $ttl);
        $this->expire($key, $ttl);
        $this->unlock($key);

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $ttl = $this->ttl($expire);

        // APC doesn't support replace; I'll use get to check key existence,
        // then safely replace with cas
        $current = $this->get($key, $token);
        if ($current === false) {
            return false;
        }

        // negative TTLs don't always seem to properly treat the key as deleted
        if ($ttl < 0) {
            $this->delete($key);

            return true;
        }

        // no need for locking - cas will do that
        return $this->cas($token, $key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $ttl = $this->ttl($expire);

        // lock required because we can't perform an atomic CAS
        if (!$this->lock($key)) {
            return false;
        }

        // check for values that were just stored in this request but have
        // actually expired by now
        if (isset($this->expires[$key]) && $this->expires[$key] < time()) {
            return false;
        }

        // get current value, to compare with token
        $compare = $this->apcu_fetch($key);

        if ($compare === false) {
            $this->unlock($key);

            return false;
        }

        if ($token !== serialize($compare)) {
            $this->unlock($key);

            return false;
        }

        // negative TTLs don't always seem to properly treat the key as deleted
        if ($ttl < 0) {
            $this->apcu_delete($key);
            unset($this->expires[$key]);
            $this->unlock($key);

            return true;
        }

        $success = $this->apcu_store($key, $value, $ttl);
        $this->expire($key, $ttl);
        $this->unlock($key);

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        // not doing apc_inc because that one it doesn't let us set an initial
        // value or TTL
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

        // not doing apc_dec because that one it doesn't let us set an initial
        // value or TTL
        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $ttl = $this->ttl($expire);

        // shortcut - expiring is similar to deleting, but the former has no
        // 1-operation equivalent
        if ($ttl < 0) {
            return $this->delete($key);
        }

        // get existing TTL & quit early if it's that one already
        $iterator = $this->APCuIterator('/^'.preg_quote($key, '/').'$/', \APC_ITER_VALUE | \APC_ITER_TTL, 1, \APC_LIST_ACTIVE);
        $current = $iterator->current();
        if (!$current) {
            // doesn't exist
            return false;
        }
        if ($current['ttl'] === $ttl) {
            // that's the TTL already, no need to reset it
            return true;
        }

        // generate CAS token to safely CAS existing value with new TTL
        $value = $current['value'];
        $token = serialize($value);

        return $this->cas($token, $key, $value, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->expires = array();

        return $this->apcu_clear_cache();
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
        $ttl = $this->ttl($expire);

        /*
         * APC has apc_inc & apc_dec, which work great. However, they don't
         * allow for a TTL to be set.
         * I could use apc_inc & apc_dec & then do a touch, but touch also
         * doesn't have an APC implementation & needs a get & cas. That would
         * be 2 operations + CAS.
         * Instead, I'll just do a get, implement the increase or decrease in
         * PHP, then CAS the new value = 1 operation + CAS.
         */
        $value = $this->get($key, $token);
        if ($value === false) {
            // don't even set initial value, it's already expired...
            if ($ttl < 0) {
                return $initial;
            }

            // no need for locking - set will do that
            $success = $this->add($key, $initial, $ttl);

            return $success ? $initial : false;
        }

        if (!is_numeric($value) || $value < 0) {
            return false;
        }

        $value += $offset;
        // value can never be lower than 0
        $value = max(0, $value);

        // negative TTLs don't always seem to properly treat the key as deleted
        if ($ttl < 0) {
            $success = $this->delete($key);

            return $success ? $value : false;
        }

        // no need for locking - cas will do that
        $success = $this->cas($token, $key, $value, $ttl);

        return $success ? $value : false;
    }

    /**
     * APC expects true TTL, not expiration timestamp.
     *
     * @param int $expire
     *
     * @return int TTL in seconds
     */
    protected function ttl($expire)
    {
        // relative time in seconds, <30 days
        if ($expire < 30 * 24 * 60 * 60) {
            $expire += time();
        }

        return $expire ? $expire - time() : 0;
    }

    /**
     * Acquire a lock. If we failed to acquire a lock, it'll automatically try
     * again in 1ms, for a maximum of 10 times.
     *
     * APC provides nothing that would allow us to do CAS. To "emulate" CAS,
     * we'll work with locks: all cache writes also briefly create a lock
     * cache entry (yup: #writes * 3, for lock & unlock - luckily, they're
     * not over the network)
     * Writes are disallows when a lock can't be obtained (= locked by
     * another write), which makes it possible for us to first retrieve,
     * compare & then set in a nob-atomic way.
     * However, there's a possibility for interference with direct APC
     * access touching the same keys - e.g. other scripts, not using this
     * class. If CAS is of importance, make sure the only things touching
     * APC on your server is using these classes!
     *
     * @param string|string[] $keys
     *
     * @return array Array of successfully locked keys
     */
    protected function lock($keys)
    {
        // both string (single key) and array (multiple) are accepted
        $keys = (array) $keys;

        $locked = array();
        for ($i = 0; $i < 10; ++$i) {
            $locked += $this->acquire($keys);
            $keys = array_diff($keys, $locked);

            if (empty($keys)) {
                break;
            }

            usleep(1);
        }

        return $locked;
    }

    /**
     * Acquire a lock - required to provide CAS functionality.
     *
     * @param string|string[] $keys
     *
     * @return string[] Array of successfully locked keys
     */
    protected function acquire($keys)
    {
        $keys = (array) $keys;

        $values = array();
        foreach ($keys as $key) {
            $values["scrapbook.lock.$key"] = null;
        }

        // there's no point in locking longer than max allowed execution time
        // for this script
        $ttl = ini_get('max_execution_time');

        // lock these keys, then compile a list of successfully locked keys
        // (using the returned failure array)
        $result = (array) $this->apcu_add($values, null, $ttl);
        $failed = array();
        foreach ($result as $key => $err) {
            $failed[] = substr($key, strlen('scrapbook.lock.'));
        }

        return array_diff($keys, $failed);
    }

    /**
     * Release a lock.
     *
     * @param string|string[] $keys
     *
     * @return bool
     */
    protected function unlock($keys)
    {
        $keys = (array) $keys;
        foreach ($keys as $i => $key) {
            $keys[$i] = "scrapbook.lock.$key";
        }

        $this->apcu_delete($keys);

        return true;
    }

    /**
     * Store the expiration time for items we're setting in this request, to
     * work around APC's behavior of only clearing expires per page request.
     *
     * @see static::$expires
     *
     * @param array|string $key
     * @param int          $ttl
     */
    protected function expire($key = array(), $ttl = 0)
    {
        if ($ttl === 0) {
            // when storing indefinitely, there's no point in keeping it around,
            // it won't just expire
            return;
        }

        // $key can be both string (1 key) or array (multiple)
        $keys = (array) $key;

        $time = time() + $ttl;
        foreach ($keys as $key) {
            $this->expires[$key] = $time;
        }
    }

    /**
     * @param string|string[] $key
     * @param bool            $success
     *
     * @return mixed|false
     */
    protected function apcu_fetch($key, &$success = null)
    {
        /*
         * $key can also be numeric, in which case APC is able to retrieve it,
         * but will have an invalid $key in the results array, and trying to
         * locate it by its $key in that array will fail with `undefined index`.
         * I'll work around this by requesting those values 1 by 1.
         */
        if (is_array($key)) {
            $nums = array_filter($key, 'is_numeric');
            if ($nums) {
                $values = [];
                foreach ($nums as $k) {
                    $values[$k] = $this->apcu_fetch((string) $k, $success);
                }

                $remaining = array_diff($key, $nums);
                if ($remaining) {
                    $values += $this->apcu_fetch($remaining, $success2);
                    $success &= $success2;
                }

                return $values;
            }
        }

        if (function_exists('apcu_fetch')) {
            return apcu_fetch($key, $success);
        } else {
            return apc_fetch($key, $success);
        }
    }

    /**
     * @param string|string[] $key
     * @param mixed           $var
     * @param int             $ttl
     *
     * @return bool|bool[]
     */
    protected function apcu_store($key, $var, $ttl = 0)
    {
        /*
         * $key can also be a [$key => $value] array, where key is numeric,
         * but got cast to int by PHP. APC doesn't seem to store such numerical
         * key, so we'll have to take care of those one by one.
         */
        if (is_array($key)) {
            $nums = array_filter(array_keys($key), 'is_numeric');
            if ($nums) {
                $success = [];
                $nums = array_intersect_key($key, array_fill_keys($nums, null));
                foreach ($nums as $k => $v) {
                    $success[$k] = $this->apcu_store((string) $k, $v, $ttl);
                }

                $remaining = array_diff_key($key, $nums);
                if ($remaining) {
                    $success += $this->apcu_store($remaining, $var, $ttl);
                }

                return $success;
            }
        }

        if (function_exists('apcu_store')) {
            return apcu_store($key, $var, $ttl);
        } else {
            return apc_store($key, $var, $ttl);
        }
    }

    /**
     * @param string|string[]|APCIterator|APCuIterator $key
     *
     * @return bool|string[]
     */
    protected function apcu_delete($key)
    {
        if (function_exists('apcu_delete')) {
            return apcu_delete($key);
        } else {
            return apc_delete($key);
        }
    }

    /**
     * @param string|string[] $key
     * @param mixed           $var
     * @param int             $ttl
     *
     * @return bool|bool[]
     */
    protected function apcu_add($key, $var, $ttl = 0)
    {
        if (function_exists('apcu_add')) {
            return apcu_add($key, $var, $ttl);
        } else {
            return apc_add($key, $var, $ttl);
        }
    }

    /**
     * @return bool
     */
    protected function apcu_clear_cache()
    {
        if (function_exists('apcu_clear_cache')) {
            return apcu_clear_cache();
        } else {
            return apc_clear_cache('user');
        }
    }

    /**
     * @param string|string[]|null $search
     * @param int                  $format
     * @param int                  $chunk_size
     * @param int                  $list
     *
     * @return APCIterator|APCuIterator
     */
    protected function APCuIterator($search = null, $format = null, $chunk_size = null, $list = null)
    {
        $arguments = func_get_args();

        if (class_exists('APCuIterator', false)) {
            // I can't set the defaults parameter values because the APC_ or
            // APCU_ constants may not exist, so I'll just initialize from
            // func_get_args, not passing those params that haven't been set
            $reflect = new \ReflectionClass('APCuIterator');

            return $reflect->newInstanceArgs($arguments);
        } else {
            array_unshift($arguments, 'user');
            $reflect = new \ReflectionClass('APCIterator');

            return $reflect->newInstanceArgs($arguments);
        }
    }
}
