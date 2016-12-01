<?php

namespace MatthiasMullie\Scrapbook\Buffered;

use MatthiasMullie\Scrapbook\Buffered\Utils\Buffer;
use MatthiasMullie\Scrapbook\Buffered\Utils\Transaction;
use MatthiasMullie\Scrapbook\Exception\UnbegunTransaction;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * In addition to buffering cache data in memory (see BufferedStore), this class
 * will add transactional capabilities. Writes can be deferred by starting a
 * transaction & all of them will only go out when you commit them.
 * This makes it possible to defer cache updates until we can guarantee it's
 * safe (e.g. until we successfully committed everything to persistent storage).
 *
 * There will be some trickery to make sure that, after we've made changes to
 * cache (but not yet committed), we don't read from the real cache anymore, but
 * instead serve the in-memory equivalent that we'll be writing to real cache
 * when all goes well.
 *
 * If a commit fails, all keys affected will be deleted to ensure no corrupt
 * data stays behind.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class TransactionalStore implements KeyValueStore
{
    /**
     * Array of KeyValueStore objects. Every cache action will be executed
     * on the last item in this array, so transactions can be nested.
     *
     * @var KeyValueStore[]
     */
    protected $transactions = array();

    /**
     * @param KeyValueStore $cache The real cache we'll buffer for
     */
    public function __construct(KeyValueStore $cache)
    {
        $this->transactions[] = $cache;
    }

    /**
     * Roll back uncommitted transactions.
     */
    public function __destruct()
    {
        while (count($this->transactions) > 1) {
            /** @var Transaction $transaction */
            $transaction = array_pop($this->transactions);
            $transaction->rollback();
        }
    }

    /**
     * Initiate a transaction: this will defer all writes to real cache until
     * commit() is called.
     */
    public function begin()
    {
        // we'll rely on buffer to respond data that has not yet committed, so
        // it must never evict from cache - I'd even rather see the app crash
        $buffer = new Buffer(ini_get('memory_limit'));

        // transactions can be nested: the previous transaction will serve as
        // cache backend for the new cache (so when committing a nested
        // transaction, it will commit to the parent transaction)
        $cache = end($this->transactions);
        $this->transactions[] = new Transaction($buffer, $cache);
    }

    /**
     * Commits all deferred updates to real cache.
     * If the any write fails, all subsequent writes will be aborted & all keys
     * that had already been written to will be deleted.
     *
     * @return bool
     *
     * @throws UnbegunTransaction
     */
    public function commit()
    {
        if (count($this->transactions) <= 1) {
            throw new UnbegunTransaction('Attempted to commit without having begun a transaction.');
        }

        /** @var Transaction $transaction */
        $transaction = array_pop($this->transactions);

        return $transaction->commit();
    }

    /**
     * Roll back all scheduled changes.
     *
     * @return bool
     *
     * @throws UnbegunTransaction
     */
    public function rollback()
    {
        if (count($this->transactions) <= 1) {
            throw new UnbegunTransaction('Attempted to rollback without having begun a transaction.');
        }

        /** @var Transaction $transaction */
        $transaction = array_pop($this->transactions);

        return $transaction->rollback();
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $cache = end($this->transactions);

        return $cache->get($key, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $cache = end($this->transactions);

        return $cache->getMulti($keys, $tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->setMulti($items, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $cache = end($this->transactions);

        return $cache->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        $cache = end($this->transactions);

        return $cache->deleteMulti($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->add($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->replace($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->increment($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $cache = end($this->transactions);

        return $cache->decrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $cache = end($this->transactions);

        return $cache->touch($key, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $cache = end($this->transactions);

        return $cache->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        $cache = end($this->transactions);

        return new static($cache->getCollection($name));
    }
}
