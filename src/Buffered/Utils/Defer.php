<?php

namespace MatthiasMullie\Scrapbook\Buffered\Utils;

use MatthiasMullie\Scrapbook\Exception\UncommittedTransaction;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This is a helper class for transactions. It will optimize the write going
 * out and take care of rolling back.
 *
 * Optimizations will be:
 * * multiple set() values (with the same expiration) will be applied in a
 *   single setMulti()
 * * for a set() followed by another set() on the same key, only the latter
 *   one will be applied
 * * same for an replace() followed by an increment(), or whatever operation
 *   happens on the same key: if we can pre-calculate the end result, we'll
 *   only execute 1 operation with the end result
 * * operations before a flush() will not be executed, they'll just be lost
 *
 * Rollback strategy includes:
 * * fetching the original value of operations prone to fail (add, replace &
 *   cas) prior to executing them
 * * executing said operations before the others, to minimize changes of
 *   interfering concurrent writes
 * * if the commit fails, said original values will be restored in case the
 *   new value had already been stored
 *
 * This class must never receive invalid data. E.g. a "replace" can never
 * follow a "delete" of the same key. This should be guaranteed by whatever
 * uses this class: there is no point in re-implementing these checks here.
 * The only acceptable conflicts are when cache values have changed outside,
 * from another process. Those will be handled by this class.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Defer
{
    /**
     * Cache to write to.
     *
     * @var KeyValueStore
     */
    protected $cache;

    /**
     * All updates will be scheduled by key. If there are multiple updates
     * for a key, they can just be folded together.
     * E.g. 2 sets, the later will override the former.
     * E.g. set + increment, might as well set incremented value immediately.
     *
     * This is going to be an array that holds horrible arrays of update data,
     * being:
     * * 0: the operation name (set, add, ...) so we're able to sort them
     * * 1: a callable, to apply the update to cache
     * * 2: the array of data to supply to the callable
     *
     * @var array[]
     */
    protected $keys = array();

    /**
     * Flush is special - it's not specific to (a) key(s), so we can't store
     * it to $keys.
     *
     * @var bool
     */
    protected $flush = false;

    /**
     * @param KeyValueStore $cache
     */
    public function __construct(KeyValueStore $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @throws UncommittedTransaction
     */
    public function __destruct()
    {
        if (!empty($this->keys)) {
            throw new UncommittedTransaction(
                'Transaction is about to be destroyed without having been '.
                'committed or rolled back.'
            );
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $expire
     */
    public function set($key, $value, $expire)
    {
        $args = array(
            'key' => $key,
            'value' => $value,
            'expire' => $expire,
        );
        $this->keys[$key] = array(__FUNCTION__, array($this->cache, __FUNCTION__), $args);
    }

    /**
     * @param mixed[] $items
     * @param int     $expire
     */
    public function setMulti(array $items, $expire)
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value, $expire);
        }
    }

    /**
     * @param string $key
     */
    public function delete($key)
    {
        $args = array('key' => $key);
        $this->keys[$key] = array(__FUNCTION__, array($this->cache, __FUNCTION__), $args);
    }

    /**
     * @param string[] $keys
     */
    public function deleteMulti(array $keys)
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $expire
     */
    public function add($key, $value, $expire)
    {
        $args = array(
            'key' => $key,
            'value' => $value,
            'expire' => $expire,
        );
        $this->keys[$key] = array(__FUNCTION__, array($this->cache, __FUNCTION__), $args);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param int    $expire
     */
    public function replace($key, $value, $expire)
    {
        $args = array(
            'key' => $key,
            'value' => $value,
            'expire' => $expire,
        );
        $this->keys[$key] = array(__FUNCTION__, array($this->cache, __FUNCTION__), $args);
    }

    /**
     * @param mixed  $originalValue No real CAS token, but the original value for this key
     * @param string $key
     * @param mixed  $value
     * @param int    $expire
     */
    public function cas($originalValue, $key, $value, $expire)
    {
        /*
         * If we made it here, we're sure that logically, the CAS applies with
         * respect to other operations in this transaction. That means we don't
         * have to verify the token here: whatever has already been set/add/
         * replace/cas will have taken care of that and we already know this one
         * applies on top op that change. We can just fold it in there & update
         * the value we set initially.
         */
        if (isset($this->keys[$key]) && in_array($this->keys[$key][0], array('set', 'add', 'replace', 'cas'))) {
            $this->keys[$key][2]['value'] = $value;
            $this->keys[$key][2]['expire'] = $expire;

            return;
        }

        /*
         * @param mixed $originalValue
         * @param string $key
         * @param mixed $value
         * @param int $expire
         * @return bool
         */
        $cache = $this->cache;
        $callback = function ($originalValue, $key, $value, $expire) use ($cache) {
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

        $args = array(
            'token' => $originalValue,
            'key' => $key,
            'value' => $value,
            'expire' => $expire,
        );
        $this->keys[$key] = array(__FUNCTION__, $callback, $args);
    }

    /**
     * @param string $key
     * @param int    $offset
     * @param int    $initial
     * @param int    $expire
     */
    public function increment($key, $offset, $initial, $expire)
    {
        $this->doIncrement(__FUNCTION__, $key, $offset, $initial, $expire);
    }

    /**
     * @param string $key
     * @param int    $offset
     * @param int    $initial
     * @param int    $expire
     */
    public function decrement($key, $offset, $initial, $expire)
    {
        $this->doIncrement(__FUNCTION__, $key, $offset, $initial, $expire);
    }

    /**
     * @param string $operation
     * @param string $key
     * @param int    $offset
     * @param int    $initial
     * @param int    $expire
     */
    protected function doIncrement($operation, $key, $offset, $initial, $expire)
    {
        if (isset($this->keys[$key])) {
            if (in_array($this->keys[$key][0], array('set', 'add', 'replace', 'cas'))) {
                // we're trying to increment a key that's only just being stored
                // in this transaction - might as well combine those
                $symbol = $this->keys[$key][1] === 'increment' ? 1 : -1;
                $this->keys[$key][2]['value'] += $symbol * $offset;
                $this->keys[$key][2]['expire'] = $expire;
            } elseif (in_array($this->keys[$key][0], array('increment', 'decrement'))) {
                // we're trying to increment a key that's already being incremented
                // or decremented in this transaction - might as well combine those

                // we may be combining an increment with a decrement
                // we must carefully figure out how these 2 apply against each other
                $symbol = $this->keys[$key][0] === 'increment' ? 1 : -1;
                $previous = $symbol * $this->keys[$key][2]['offset'];

                $symbol = $operation === 'increment' ? 1 : -1;
                $current = $symbol * $offset;

                $offset = $previous + $current;

                $this->keys[$key][2]['offset'] = abs($offset);
                // initial value must also be adjusted to include the new offset
                $this->keys[$key][2]['initial'] += $current;
                $this->keys[$key][2]['expire'] = $expire;

                // adjust operation - it might just have switched from increment to
                // decrement or vice versa
                $operation = $offset >= 0 ? 'increment' : 'decrement';
                $this->keys[$key][0] = $operation;
                $this->keys[$key][1] = array($this->cache, $operation);
            } else {
                // touch & delete become useless if incrementing/decrementing after
                unset($this->keys[$key]);
            }
        }

        if (!isset($this->keys[$key])) {
            $args = array(
                'key' => $key,
                'offset' => $offset,
                'initial' => $initial,
                'expire' => $expire,
            );
            $this->keys[$key] = array($operation, array($this->cache, $operation), $args);
        }
    }

    /**
     * @param string $key
     * @param int    $expire
     */
    public function touch($key, $expire)
    {
        if (isset($this->keys[$key]) && isset($this->keys[$key][2]['expire'])) {
            // changing expiration time of a value we're already storing in
            // this transaction - might as well just set new expiration time
            // right away
            $this->keys[$key][2]['expire'] = $expire;
        } else {
            $args = array(
                'key' => $key,
                'expire' => $expire,
            );
            $this->keys[$key] = array(__FUNCTION__, array($this->cache, __FUNCTION__), $args);
        }
    }

    public function flush()
    {
        // clear all scheduled updates, they'll be wiped out after this anyway
        $this->keys = array();
        $this->flush = true;
    }

    /**
     * Clears all scheduled writes.
     */
    public function clear()
    {
        $this->keys = array();
        $this->flush = false;
    }

    /**
     * Commit all deferred writes to cache.
     *
     * When the commit fails, no changes in this transaction will be applied
     * (and those that had already been applied will be undone). False will
     * be returned in that case.
     *
     * @return bool
     */
    public function commit()
    {
        list($old, $new) = $this->generateRollback();
        $updates = $this->generateUpdates();
        $updates = $this->combineUpdates($updates);
        usort($updates, array($this, 'sortUpdates'));

        foreach ($updates as $update) {
            // apply update to cache & receive a simple bool to indicate
            // success (true) or failure (false)
            $success = call_user_func_array($update[1], $update[2]);
            if ($success === false) {
                $this->rollback($old, $new);

                return false;
            }
        }

        $this->clear();

        return true;
    }

    /**
     * Roll the cache back to pre-transaction state by comparing the current
     * cache values with what we planned to set them to.
     *
     * @param array $old
     * @param array $new
     */
    protected function rollback(array $old, array $new)
    {
        foreach ($old as $key => $value) {
            $current = $this->cache->get($key, $token);

            /*
             * If the value right now equals the one we planned to write, it
             * should be restored to what it was before. If it's yet something
             * else, another process must've stored it and we should leave it
             * alone.
             */
            if ($current === $new) {
                /*
                 * CAS the rollback. If it fails, that means another process
                 * has stored in the meantime and we can just leave it alone.
                 * Note that we can't know the original expiration time!
                 */
                $this->cas($token, $key, $value, 0);
            }
        }

        $this->clear();
    }

    /**
     * Since we can't perform true atomic transactions, we'll fake it.
     * Most of the operations (set, touch, ...) can't fail. We'll do those last.
     * We'll first schedule the operations that can fail (cas, replace, add)
     * to minimize chances of another process overwriting those values in the
     * meantime.
     * But it could still happen, so we should fetch the current values for all
     * unsafe operations. If the transaction fails, we can then restore them.
     *
     * @return array[] Array of 2 [key => value] maps: current & scheduled data
     */
    protected function generateRollback()
    {
        $keys = array();
        $new = array();

        foreach ($this->keys as $key => $data) {
            $operation = $data[0];

            // we only need values for cas & replace - recovering from an 'add'
            // is just deleting the value...
            if (in_array($operation, array('cas', 'replace'))) {
                $keys[] = $key;
                $new[$key] = $data[2]['value'];
            }
        }

        if (empty($keys)) {
            return array(array(), array());
        }

        // fetch the existing data & return the planned new data as well
        $current = $this->cache->getMulti($keys);

        return array($current, $new);
    }

    /**
     * By storing all updates by key, we've already made sure we don't perform
     * redundant operations on a per-key basis. Now we'll turn those into
     * actual updates.
     *
     * @return array
     */
    protected function generateUpdates()
    {
        $updates = array();

        if ($this->flush) {
            $updates[] = array('flush', array($this->cache, 'flush'), array());
        }

        foreach ($this->keys as $key => $data) {
            $updates[] = $data;
        }

        return $updates;
    }

    /**
     * We may have multiple sets & deletes, which can be combined into a single
     * setMulti or deleteMulti operation.
     *
     * @param array $updates
     *
     * @return array
     */
    protected function combineUpdates($updates)
    {
        $setMulti = array();
        $deleteMulti = array();

        foreach ($updates as $i => $update) {
            $operation = $update[0];
            $args = $update[2];

            switch ($operation) {
                // all set & delete operations can be grouped into setMulti & deleteMulti
                case 'set':
                    unset($updates[$i]);

                    // only group sets with same expiration
                    $setMulti[$args['expire']][$args['key']] = $args['value'];
                    break;
                case 'delete':
                    unset($updates[$i]);

                    $deleteMulti[] = $args['key'];
                    break;
                default:
                    break;
            }
        }

        if (!empty($setMulti)) {
            $cache = $this->cache;

            /*
             * We'll use the return value of all deferred writes to check if they
             * should be rolled back.
             * commit() expects a single bool, not a per-key array of success bools.
             *
             * @param mixed[] $items
             * @param int $expire
             * @return bool
             */
            $callback = function ($items, $expire) use ($cache) {
                $success = $cache->setMulti($items, $expire);

                return !in_array(false, $success);
            };

            foreach ($setMulti as $expire => $items) {
                $updates[] = array('setMulti', $callback, array($items, $expire));
            }
        }

        if (!empty($deleteMulti)) {
            $cache = $this->cache;

            /*
             * commit() expected a single bool, not an array of success bools.
             * Besides, deleteMulti() is never cause for failure here: if the
             * key didn't exist because it has been deleted elsewhere already,
             * the data isn't corrupt, it's still as we'd expect it.
             *
             * @param string[] $keys
             * @return bool
             */
            $callback = function ($keys) use ($cache) {
                $cache->deleteMulti($keys);

                return true;
            };

            $updates[] = array('deleteMulti', $callback, array($deleteMulti));
        }

        return $updates;
    }

    /**
     * Change the order of the updates in this transaction to ensure we have those
     * most likely to fail first. That'll decrease odds of having to roll back, and
     * make rolling back easier.
     *
     * @param array $a Update, where index 0 is the operation name
     * @param array $b Update, where index 0 is the operation name
     *
     * @return int
     */
    protected function sortUpdates(array $a, array $b)
    {
        $updateOrder = array(
            // there's no point in applying this after doing the below updates
            // we also shouldn't really worry about cas/replace failing after this,
            // there won't be any after cache having been flushed
            'flush',

            // prone to fail: they depend on certain conditions (token must match
            // or value must (not) exist)
            'cas',
            'replace',
            'add',

            // unlikely/impossible to fail, assuming the input is valid
            'touch',
            'increment',
            'decrement',
            'set', 'setMulti',
            'delete', 'deleteMulti',
        );

        if ($a[0] === $b[0]) {
            return 0;
        }

        return array_search($a[0], $updateOrder) < array_search($b[0], $updateOrder) ? -1 : 1;
    }
}
