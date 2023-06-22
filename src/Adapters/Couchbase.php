<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters;

use DateTime;
use MatthiasMullie\Scrapbook\Adapters\Collections\Couchbase as Collection;
use MatthiasMullie\Scrapbook\Exception\InvalidKey;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Couchbase adapter. Basically just a wrapper over \CouchbaseBucket, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @see https://docs.couchbase.com/sdk-api/couchbase-php-client/namespaces/couchbase.html
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Couchbase implements KeyValueStore
{
    protected \Couchbase\Collection $collection;

    protected \Couchbase\BucketManager|\Couchbase\Management\BucketManager $bucketManager;

    protected \Couchbase\Bucket $bucket;

    /**
     * @var int|null Timeout in ms
     */
    protected int|null $timeout;

    /**
     * @param int|null $timeout K/V timeout in ms
     */
    public function __construct(
        \Couchbase\Collection $client,
        \Couchbase\BucketManager|\Couchbase\Management\BucketManager $bucketManager,
        \Couchbase\Bucket $bucket,
        int $timeout = null
    ) {
        $this->collection = $client;
        $this->bucketManager = $bucketManager;
        $this->bucket = $bucket;
        $this->timeout = $timeout;
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        $this->assertValidKey($key);

        try {
            $options = new \Couchbase\GetOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $result = $this->collection->get($key, $options);
            $token = $result->cas();

            return $this->unserialize($result->content());
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            $token = null;

            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            $token = null;

            return false;
        }
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        // SDK >=3.0 no longer provides *multi operations
        $results = [];
        $tokens = [];
        foreach ($keys as $key) {
            $token = null;
            $value = $this->get($key, $token);

            if ($token !== null) {
                $results[$key] = $value;
                $tokens[$key] = $token;
            }
        }

        return $results;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        $this->assertValidKey($key);

        if ($this->deleteIfExpired($key, $expire)) {
            return true;
        }

        $value = $this->serialize($value);

        try {
            $options = new \Couchbase\UpsertOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $options = $options->expiry($this->expire($expire));
            $this->collection->upsert($key, $value, $options);

            return true;
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            return false;
        }
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        // SDK >=3.0 no longer provides *multi operations
        $success = [];
        foreach ($items as $key => $value) {
            // PHP treats numeric keys as integers, but they're allowed
            $key = (string) $key;
            $success[$key] = $this->set($key, $value, $expire);
        }

        return $success;
    }

    public function delete(string $key): bool
    {
        $this->assertValidKey($key);

        try {
            $options = new \Couchbase\RemoveOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $this->collection->remove($key, $options);

            return true;
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            return false;
        }
    }

    public function deleteMulti(array $keys): array
    {
        // SDK >=3.0 no longer provides *multi operations
        $success = [];
        foreach ($keys as $key) {
            $success[$key] = $this->delete($key);
        }

        return $success;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        $this->assertValidKey($key);

        $value = $this->serialize($value);

        try {
            $options = new \Couchbase\InsertOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $options = $options->expiry($this->expire($expire));
            $this->collection->insert($key, $value, $options);

            $this->deleteIfExpired($key, $expire);

            return true;
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            return false;
        }
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        $this->assertValidKey($key);

        $value = $this->serialize($value);

        try {
            $options = new \Couchbase\ReplaceOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $options = $options->expiry($this->expire($expire));
            $this->collection->replace($key, $value, $options);

            $this->deleteIfExpired($key, $expire);

            return true;
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            return false;
        }
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        $this->assertValidKey($key);

        $value = $this->serialize($value);

        if ($token === null) {
            return false;
        }
        try {
            $options = new \Couchbase\ReplaceOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $options = $options->expiry($this->expire($expire));
            $options = $options->cas($token);
            $this->collection->replace($key, $value, $options);

            $this->deleteIfExpired($key, $expire);

            return true;
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            return false;
        }
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        $this->assertValidKey($key);

        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        $this->assertValidKey($key);

        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    public function touch(string $key, int $expire): bool
    {
        $this->assertValidKey($key);

        if ($this->deleteIfExpired($key, $expire)) {
            return true;
        }

        try {
            $options = new \Couchbase\GetAndTouchOptions();
            $options = $this->timeout === null ? $options : $options->timeout($this->timeout);
            $this->collection->getAndTouch($key, $this->expire($expire), $options);

            return true;
        } catch (\Couchbase\Exception\CouchbaseException $e) {
            // SDK >=4.0
            return false;
        } catch (\Couchbase\BaseException $e) {
            // SDK >=3.0 & <4.0
            return false;
        }
    }

    public function flush(): bool
    {
        $bucketSettings = $this->bucketManager->getBucket($this->bucket->name());
        if (!$bucketSettings->flushEnabled()) {
            // `enableFlush` exists, but whether or not it is enabled is config
            // that doesn't belong here; Scrapbook shouldn't alter that
            return false;
        }

        $this->bucketManager->flush($this->bucket->name());

        return true;
    }

    public function getCollection(string $name): KeyValueStore
    {
        return new Collection($this, $name);
    }

    /**
     * We could use `$this->collection->counter()`, but it doesn't seem to respect
     * data types and stores the values as strings instead of integers.
     *
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * split up (increment won't accept negative values).
     */
    protected function doIncrement(string $key, int $offset, int $initial, int $expire): int|false
    {
        $this->assertValidKey($key);

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
        $success = $this->cas($token, $key, $value, $expire);

        return $success ? $value : false;
    }

    /**
     * Couchbase doesn't properly remember the data type being stored:
     * arrays and objects are turned into stdClass instances, or the
     * other way around.
     */
    protected function serialize(mixed $value): mixed
    {
        // binary data doesn't roundtrip well
        if (is_string($value) && !preg_match('//u', $value)) {
            return serialize(base64_encode($value));
        }

        // and neither do arrays/objects
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return $value;
    }

    /**
     * Restore serialized data.
     */
    protected function unserialize(mixed $value): mixed
    {
        // more efficient quick check whether value is unserializable
        if (!is_string($value) || !preg_match('/^[saOC]:[0-9]+:/', $value)) {
            return $value;
        }

        $unserialized = @unserialize($value, ['allowed_classes' => true]);
        if ($unserialized === false) {
            return $value;
        }

        if (is_string($unserialized)) {
            return base64_decode($unserialized);
        }

        return $unserialized;
    }

    /**
     * Couchbase seems to not timely purge items the way it should when
     * storing it with an expired timestamp, so we'll detect that and
     * delete it (instead of performing the already expired operation).
     *
     * @param string|string[] $key
     *
     * @return bool True if expired
     */
    protected function deleteIfExpired(string|array $key, int $expire): bool
    {
        if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
            $this->deleteMulti((array) $key);

            return true;
        }

        return false;
    }

    /**
     * Couchbase expects an integer TTL (under 1576800000) for relative
     * times, or a \DateTimeInterface for absolute times.
     *
     * @return int|\DateTime expiration in seconds or \DateTimeInterface
     */
    protected function expire(int $expire): int|\DateTime
    {
        // relative time in seconds, <30 days
        if ($expire < 30 * 24 * 60 * 60) {
            flush();

            return $expire;
        }

        if ($expire < time()) {
            // a timestamp (whether int or DateTimeInterface) larger than
            // 1576800000 is not accepted; let's just go with -1, result
            // is the same: it's expired & should be evicted
            return -1; // @todo this if statement should be useless; this case should be fine as DateTime
        }

        return (new \DateTime())->setTimestamp($expire);
    }

    /**
     * @throws InvalidKey
     */
    protected function assertValidKey(string $key): void
    {
        if (strlen($key) > 255) {
            throw new InvalidKey("Invalid key: $key. Couchbase keys can not exceed 255 chars.");
        }
    }
}
