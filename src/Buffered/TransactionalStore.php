<?php

namespace MatthiasMullie\Scrapbook\Buffered;

/**
 * In addition to buffering cache data in memory (see BufferedStore), this class
 * will add transactional capabilities. Writes can be deferred by starting a
 * transaction & all of them will only go out when you commit them.
 * This makes it possible to defer cache updates until we can guarantee it's
 * safe (e.g. until we successfully committed everything to persistent storage)
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
 *
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class TransactionalStore extends BufferedStore
{
    /**
     * Deferred updates to be committed to real cache.
     *
     * @see defer()
     * @var array
     */
    protected $buffer = array();

    /**
     * Whether or not to defer updates.
     *
     * @var bool
     */
    protected $transaction = false;

    /**
     * Array of keys we've written to. They'll briefly be stored here after
     * being committed, until all other writes in the transaction have been
     * committed. This way, if a later write fails, we can invalidate previous
     * updates based on those keys we wrote to.
     *
     * @see commit()
     * @var string[]
     */
    protected $committed = array();

    /**
     * Suspend reads from real cache. This is used when a flush is issued but it
     * has not yet been committed. In that case, we don't want to fall back to
     * real cache values, because they're about to be flushed.
     *
     * @var bool
     */
    protected $suspend = false;

    /**
     * Initiate a transaction: this will defer all writes to real cache until
     * commit() is called.
     */
    public function begin()
    {
        $this->transaction = true;
    }

    /**
     * Commits all deferred updates to real cache.
     * If the any write fails, all subsequent writes will be aborted & all keys
     * that had already been written to will be deleted.
     *
     * @return bool
     */
    public function commit()
    {
        foreach ($this->buffer as $update) {
            $success = call_user_func_array($update[0], $update[1]);

            // store keys that data has been written to (so we can rollback)
            $this->committed += array_flip($update[2]);

            // if we failed to commit data at any point, roll back
            if ($success === false) {
                $this->rollback();

                return false;
            }
        }

        $this->clearLocal();
        $this->transaction = false;
        $this->suspend = false;

        return true;
    }

    /**
     * Roll back all scheduled changes.
     */
    public function rollback()
    {
        // delete all those keys from cache, they may be corrupt
        foreach ($this->committed as $key => $nop) {
            $this->cache->delete($key);
        }

        // always clear local cache values when something went wrong
        $this->clearLocal();
        $this->transaction = false;
        $this->suspend = false;
    }

    /**
     * {@inheritDoc}
     */
    public function get($key, &$token = null)
    {
        // short-circuit reading from real cache if we have an uncommitted flush
        if ($this->suspend) {
            $value = $this->local->get($key);
            if ($value === false) {
                // flush hasn't been committed yet, don't read from real cache!
                return false;
            }
        }

        // at this point, we're certain that what parent (who needs to take care
        // of token etc) will only read from local cache, or we're not in
        // suspend mode
        return parent::get($key, $token);
    }

    /**
     * {@inheritDoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        if (!$this->suspend) {
            return parent::getMulti($keys, $tokens);
        }

        // short-circuit reading from real cache if we have an uncommitted flush

        // figure out which missing key we need to get from real cache
        $values = $this->local->getMulti($keys);
        $missing = array_diff($keys, array_keys($values));

        // temporarily mark them as expired, so parent::getMulti will not reach
        // out to real cache for them
        $this->local->setMulti(array_fill_keys($missing, ''), -1);

        $values = parent::getMulti($keys, $tokens);

        // clean up local cache again now
        $this->local->deleteMulti($missing);

        return $values;
    }

    /**
     * {@inheritDoc}
     */
    public function flush()
    {
        // make sure that reads, from now on until commit, don't read from cache
        $this->suspend = true;

        return parent::flush();
    }

    /**
     * {@inheritDoc)
     */
    protected function defer($callback, $arguments, $key)
    {
        // keys can be either 1 single string or array of multiple keys
        $keys = (array) $key;

        $this->buffer[] = array($callback, $arguments, $keys);

        // persist to real cache immediately, if we're not in a "transaction"
        if (!$this->transaction) {
            return $this->commit();
        }

        return true;
    }

    /**
     * Clears all data stored in memory.
     */
    protected function clearLocal()
    {
        $this->local->flush();
        $this->buffer = array();
        $this->committed = array();
        $this->tokens = array();
    }
}
