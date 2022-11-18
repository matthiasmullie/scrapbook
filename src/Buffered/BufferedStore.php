<?php

declare(strict_types=1);

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
     */
    protected Transaction $transaction;

    /**
     * Local in-memory storage, for the data we've already requested from
     * or written to the real cache.
     */
    protected Buffer $local;

    /**
     * @var BufferedStore[]
     */
    protected array $collections = [];

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
    public function get(string $key, mixed &$token = null): mixed
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
    public function getMulti(array $keys, array &$tokens = null): array
    {
        $values = $this->transaction->getMulti($keys, $tokens);

        $missing = array_diff_key($values, $this->local->getMulti($keys));
        if (!empty($missing)) {
            $this->local->setMulti($missing);
        }

        return $values;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        $result = $this->transaction->set($key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        $result = $this->transaction->setMulti($items, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function delete(string $key): bool
    {
        $result = $this->transaction->delete($key);
        $this->transaction->commit();

        return $result;
    }

    public function deleteMulti(array $keys): array
    {
        $result = $this->transaction->deleteMulti($keys);
        $this->transaction->commit();

        return $result;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        $result = $this->transaction->add($key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        $result = $this->transaction->replace($key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        $result = $this->transaction->cas($token, $key, $value, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        $result = $this->transaction->increment($key, $offset, $initial, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        $result = $this->transaction->decrement($key, $offset, $initial, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function touch(string $key, int $expire): bool
    {
        $result = $this->transaction->touch($key, $expire);
        $this->transaction->commit();

        return $result;
    }

    public function flush(): bool
    {
        foreach ($this->collections as $collection) {
            $collection->flush();
        }

        $result = $this->transaction->flush();
        $this->transaction->commit();

        return $result;
    }

    public function getCollection(string $name): KeyValueStore
    {
        if (!isset($this->collections[$name])) {
            $collection = $this->transaction->getCollection($name);
            $this->collections[$name] = new static($collection);
        }

        return $this->collections[$name];
    }
}
