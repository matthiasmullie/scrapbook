<?php

declare(strict_types=1);

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
    protected array $transactions = [];

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
    public function begin(): void
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
     * @throws UnbegunTransaction
     */
    public function commit(): bool
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
     * @throws UnbegunTransaction
     */
    public function rollback(): bool
    {
        if (count($this->transactions) <= 1) {
            throw new UnbegunTransaction('Attempted to rollback without having begun a transaction.');
        }

        /** @var Transaction $transaction */
        $transaction = array_pop($this->transactions);

        return $transaction->rollback();
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        return end($this->transactions)->get($key, $token);
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        return end($this->transactions)->getMulti($keys, $tokens);
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        return end($this->transactions)->set($key, $value, $expire);
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        return end($this->transactions)->setMulti($items, $expire);
    }

    public function delete(string $key): bool
    {
        return end($this->transactions)->delete($key);
    }

    public function deleteMulti(array $keys): array
    {
        return end($this->transactions)->deleteMulti($keys);
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        return end($this->transactions)->add($key, $value, $expire);
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        return end($this->transactions)->replace($key, $value, $expire);
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        return end($this->transactions)->cas($token, $key, $value, $expire);
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        return end($this->transactions)->increment($key, $offset, $initial, $expire);
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        return end($this->transactions)->decrement($key, $offset, $initial, $expire);
    }

    public function touch(string $key, int $expire): bool
    {
        return end($this->transactions)->touch($key, $expire);
    }

    public function flush(): bool
    {
        return end($this->transactions)->flush();
    }

    public function getCollection(string $name): KeyValueStore
    {
        return new static(end($this->transactions)->getCollection($name));
    }
}
