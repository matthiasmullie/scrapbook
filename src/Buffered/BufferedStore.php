<?php

namespace MatthiasMullie\Scrapbook\Buffered;

use MatthiasMullie\Scrapbook\Buffered\Utils\Buffer;
use MatthiasMullie\Scrapbook\Buffered\Utils\Transaction;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * This class will serve as a local buffer to the real cache: anything read from
 * & written to the real cache will be stored in memory, so if any of those keys
 * is again requested in the same request, we can just grab it from memory
 * instead of having to get it over the wire.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class BufferedStore implements KeyValueStore
{
    /**
     * Transaction will already buffer all writes (until the transaction
     * has been committed/rolled back). As long as we immediately commit
     * to real store, it'll look as if no transaction is in progress &
     * all we'll be left with is the local copy of all data that can act
     * as buffer for follow-up requests.
     * All we'll need to add is also buffering non-write results.
     *
     * @var Transaction
     */
    protected $transaction;

    /**
     * Local in-memory storage, for the data we've already requested from
     * or written to the real cache.
     *
     * @var Buffer
     */
    protected $local;

    /**
     * @var BufferedStore[]
     */
    protected $collections = array();

    /**
     * @param KeyValueStore $cache The real cache we'll buffer for
     */
    public function __construct(KeyValueStore $cache)
    {
        $this->local = new Buffer();
        $this->transaction = new Transaction($this->local, $cache);
    }

    /**
     * In addition to all writes being stored to $local, we'll also
     * keep get() values around ;).
     *
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $value = $this->transaction->get($key, $token);

        // only store if we managed to retrieve a value (valid token) and it's
        // not already in cache (or we may mess up tokens)
        if ($value !== false && $this->local->get($key, $localToken) === false && $localToken === null) {
            $this->local->set($key, $value);
        }

        return $value;
    }

    /**
     * In addition to all writes being stored to $local, we'll also
     * keep get() values around ;).
     *
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $values = $this->transaction->getMulti($keys, $tokens);

        $missing = array_diff_key($values, $this->local->getMulti($keys));
        if (!empty($missing)) {
            $this->local->setMulti($missing);
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $result = $this->transaction->set($key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $result = $this->transaction->setMulti($items, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $result = $this->transaction->delete($key);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        $result = $this->transaction->deleteMulti($keys);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $result = $this->transaction->add($key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $result = $this->transaction->replace($key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $result = $this->transaction->cas($token, $key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $result = $this->transaction->increment($key, $offset, $initial, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $result = $this->transaction->decrement($key, $offset, $initial, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $result = $this->transaction->touch($key, $expire);
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        foreach ($this->collections as $collection) {
            $collection->flush();
        }

        $result = $this->transaction->flush();
        $this->transaction->commit();

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        if (!isset($this->collections[$name])) {
            $collection = $this->transaction->getCollection($name);
            $this->collections[$name] = new static($collection);
        }

        return $this->collections[$name];
    }
}
