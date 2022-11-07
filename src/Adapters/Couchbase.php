<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\Adapters\Collections\Couchbase as Collection;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Exception\InvalidKey;
use MatthiasMullie\Scrapbook\Exception\ServerUnhealthy;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Couchbase adapter. Basically just a wrapper over \CouchbaseBucket, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @see http://developer.couchbase.com/documentation/server/4.0/sdks/php-2.0/php-intro.html
 * @see http://docs.couchbase.com/sdk-api/couchbase-php-client-2.1.0/
 * @see http://docs.couchbase.com/sdk-api/couchbase-php-client-2.6.2/
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Couchbase implements KeyValueStore
{
    /**
     * @var \CouchbaseBucket|\Couchbase\Bucket|\Couchbase\Collection
     *                                                               \CouchbaseBucket for Couchbase SDK <=2.2,
     *                                                               \Couchbase\Bucket for SDK >=2.3 & <3.0,
     *                                                               \Couchbase\Collection for SDK >=3.0
     */
    protected $collection;

    /**
     * @var \CouchbaseBucketManager|\Couchbase\BucketManager|\Couchbase\Management\BucketManager
     *                                                                                           \CouchbaseBucketManager for Couchbase SDK <=2.2,
     *                                                                                           \Couchbase\BucketManager for SDK >=2.3 & <4.0,
     *                                                                                           \Couchbase\Management\BucketManager for SDK >=4.0
     */
    protected $bucketManager;

    /**
     * @var \CouchbaseBucket|\Couchbase\Bucket
     *                                         \CouchbaseBucket for Couchbase SDK <=2.2,
     *                                         \Couchbase\Bucket for SDK >=2.3
     */
    protected $bucket;

    /**
     * @var int|null Timeout in ms
     */
    protected $timeout;

    /**
     * @param \CouchbaseBucket|\Couchbase\Bucket|\Couchbase\Collection                                   $client
     *                                                                                                                  \CouchbaseBucket for Couchbase SDK <=2.2,
     *                                                                                                                  \Couchbase\Bucket for SDK >=2.3 & <3.0,
     *                                                                                                                  \Couchbase\Collection for SDK >=3.0
     * @param \CouchbaseBucketManager|\Couchbase\BucketManager|\Couchbase\Management\BucketManager|false $bucketManager
     *                                                                                                                  \CouchbaseBucketManager for Couchbase SDK <=2.2,
     *                                                                                                                  \Couchbase\BucketManager for SDK >=2.3 & <4.0,
     *                                                                                                                  \Couchbase\Management\BucketManager for SDK >=4.0,
     *                                                                                                                  false for compatibility with when this 2nd argument was $assertServerHealthy
     * @param \CouchbaseBucket|\Couchbase\Bucket                                                         $bucketManager
     *                                                                                                                  \CouchbaseBucket for Couchbase SDK <=2.2,
     *                                                                                                                  \Couchbase\Bucket for SDK >=2.3,
     *                                                                                                                  null for compatibility with when this argument didn't yet exist
     * @param int                                                                                        $timeout       K/V timeout in ms
     *
     * @throws ServerUnhealthy
     */
    public function __construct(
        /* \CouchbaseBucket|\Couchbase\Bucket|\Couchbase\Collection */
        $client,
        /* \CouchbaseBucketManager|\Couchbase\BucketManager|\Couchbase\Management\BucketManager|false */
        $bucketManager,
        /* \CouchbaseBucket|\Couchbase\Bucket|null */
        $bucket,
        /* int|null */
        $timeout = null
    ) {
        // BC: $assertServerHealthy used to be 2nd argument
        $assertServerHealthy = is_bool($bucketManager) ? $bucketManager : false;
        $this->timeout = $timeout;

        if ($client instanceof \CouchbaseBucket) {
            // SDK <=2.2
            $this->collection = $client;
            $this->bucket = $bucket instanceof \CouchbaseBucket ? $bucket : $client;

            if ($bucketManager instanceof \CouchbaseBucketManager) {
                $this->bucketManager = $bucketManager;
            } else {
                $this->bucketManager = $client->manager();
            }

            if ($assertServerHealthy) {
                $info = $this->bucketManager->info();
                foreach ($info['nodes'] as $node) {
                    if ('healthy' !== $node['status']) {
                        throw new ServerUnhealthy('Server isn\'t ready yet');
                    }
                }
            }
        } elseif ($client instanceof \Couchbase\Bucket && !method_exists($client, 'defaultCollection')) {
            // SDK <3.0
            $this->collection = $client;
            $this->bucket = $bucket instanceof \Couchbase\Bucket ? $bucket : $client;

            if ($bucketManager instanceof \Couchbase\BucketManager) {
                $this->bucketManager = $bucketManager;
            } elseif (method_exists($client, 'manager')) {
                $this->bucketManager = $client->manager();
            }

            if ($assertServerHealthy) {
                $info = $this->bucket->ping();
                foreach ($info['services']['kv'] as $kv) {
                    if ('ok' !== $kv['state']) {
                        throw new ServerUnhealthy('Server isn\'t ready yet');
                    }
                }
            }
        } elseif (
            $client instanceof \Couchbase\Collection && $bucket instanceof \Couchbase\Bucket &&
            (
                // SDK >= 3.0 & < 4.0
                $bucketManager instanceof \Couchbase\BucketManager ||
                // SDK >= 4.0
                $bucketManager instanceof \Couchbase\Management\BucketManager
            )
        ) {
            $this->collection = $client;
            $this->bucketManager = $bucketManager;
            $this->bucket = $bucket;
        } elseif (
            // received bucket for client, but since we didn't go down the SDK <3.0
            // path, we're on a more recent SDK & should've received a collection
            $client instanceof \Couchbase\Bucket ||
            // received collection, but other params are invalid
            $client instanceof \Couchbase\Collection
        ) {
            throw new Exception('Invalid Couchbase adapter constructor arguments. \Couchbase\Collection, \Couchbase\BucketManager & \Couchbase\Bucket arguments are required for Couchbase SDK >= 3.x or 4.x');
        } else {
            throw new Exception('Invalid Couchbase adapter constructor arguments');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $this->assertValidKey($key);

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            try {
                $options = new \Couchbase\GetOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
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

        // SDK <3.0
        try {
            $result = $this->collection->get($key);
        } catch (\CouchbaseException $e) {
            $token = null;

            return false;
        }

        $token = $result->cas;

        return $result->error ? false : $this->unserialize($result->value);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0 no longer provides *multi operations
            $results = array();
            $tokens = array();
            foreach ($keys as $key) {
                $token = null;
                $value = $this->get($key, $token);

                if (null !== $token) {
                    $results[$key] = $value;
                    $tokens[$key] = $token;
                }
            }

            return $results;
        }

        // SDK <3.0
        array_map(array($this, 'assertValidKey'), $keys);

        $tokens = array();
        if (empty($keys)) {
            return array();
        }

        try {
            $results = $this->collection->get($keys);
        } catch (\CouchbaseException $e) {
            return array();
        }

        $values = array();
        $tokens = array();

        foreach ($results as $key => $value) {
            if (!in_array($key, $keys) || $value->error) {
                continue;
            }

            $values[$key] = $this->unserialize($value->value);
            $tokens[$key] = $value->cas;
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $this->assertValidKey($key);

        if ($this->deleteIfExpired($key, $expire)) {
            return true;
        }

        $value = $this->serialize($value);

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            try {
                $options = new \Couchbase\UpsertOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
                $options = $options->expiry($expire);
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

        // SDK <3.0
        try {
            $options = array('expiry' => $expire);
            $result = $this->collection->upsert($key, $value, $options);
        } catch (\CouchbaseException $e) {
            return false;
        }

        return !$result->error;
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0 no longer provides *multi operations
            $success = array();
            foreach ($items as $key => $value) {
                $success[$key] = $this->set($key, $value, $expire);
            }

            return $success;
        }

        // SDK <3.0
        array_map(array($this, 'assertValidKey'), array_keys($items));

        if (empty($items)) {
            return array();
        }

        $keys = array_keys($items);
        if ($this->deleteIfExpired($keys, $expire)) {
            return array_fill_keys($keys, true);
        }

        // attempting to insert integer keys (e.g. '0' as key is automatically
        // cast to int, if it's an array key) fails with a segfault, so we'll
        // have to do those piecemeal
        $integers = array_filter(array_keys($items), 'is_int');
        if ($integers) {
            $success = array();
            $integers = array_intersect_key($items, array_fill_keys($integers, null));
            foreach ($integers as $k => $v) {
                $success[$k] = $this->set((string) $k, $v, $expire);
            }

            $items = array_diff_key($items, $integers);

            return array_merge($success, $this->setMulti($items, $expire));
        }

        foreach ($items as $key => $value) {
            $items[$key] = array(
                'value' => $this->serialize($value),
                'expiry' => $expire,
            );
        }

        try {
            $results = $this->collection->upsert($items);
        } catch (\CouchbaseException $e) {
            return array_fill_keys(array_keys($items), false);
        }

        $success = array();
        foreach ($results as $key => $result) {
            $success[$key] = !$result->error;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $this->assertValidKey($key);

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            try {
                $options = new \Couchbase\RemoveOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
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

        // SDK <3.0
        try {
            $result = $this->collection->remove($key);
        } catch (\CouchbaseException $e) {
            return false;
        }

        return !$result->error;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0 no longer provides *multi operations
            $success = array();
            foreach ($keys as $key) {
                $success[$key] = $this->delete($key);
            }

            return $success;
        }

        // SDK <3.0
        array_map(array($this, 'assertValidKey'), $keys);

        if (empty($keys)) {
            return array();
        }

        try {
            $results = $this->collection->remove($keys);
        } catch (\CouchbaseException $e) {
            return array_fill_keys($keys, false);
        }

        $success = array();
        foreach ($results as $key => $result) {
            $success[$key] = !$result->error;
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $this->assertValidKey($key);

        $value = $this->serialize($value);

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            try {
                $options = new \Couchbase\InsertOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
                $options = $options->expiry($expire);
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

        // SDK <3.0
        try {
            $options = array('expiry' => $expire);
            $result = $this->collection->insert($key, $value, $options);
        } catch (\CouchbaseException $e) {
            return false;
        }

        $success = !$result->error;

        // Couchbase is imprecise in its expiration handling, so we can clean up
        // stuff that is already expired (assuming the `add` succeeded)
        if ($success) {
            $this->deleteIfExpired($key, $expire);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $this->assertValidKey($key);

        $value = $this->serialize($value);

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            try {
                $options = new \Couchbase\ReplaceOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
                $options = $options->expiry($expire);
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

        // SDK <3.0
        try {
            $options = array('expiry' => $expire);
            $result = $this->collection->replace($key, $value, $options);
        } catch (\CouchbaseException $e) {
            return false;
        }

        $success = !$result->error;

        // Couchbase is imprecise in its expiration handling, so we can clean up
        // stuff that is already expired (assuming the `replace` succeeded)
        if ($success) {
            $this->deleteIfExpired($key, $expire);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $this->assertValidKey($key);

        $value = $this->serialize($value);

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            if (null === $token) {
                return false;
            }
            try {
                $options = new \Couchbase\ReplaceOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
                $options = $options->expiry($expire);
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

        // SDK <3.0
        try {
            $options = array('expiry' => $expire, 'cas' => $token);
            $result = $this->collection->replace($key, $value, $options);
        } catch (\CouchbaseException $e) {
            return false;
        }

        $success = !$result->error;

        // Couchbase is imprecise in its expiration handling, so we can clean up
        // stuff that is already expired (assuming the `cas` succeeded)
        if ($success) {
            $this->deleteIfExpired($key, $expire);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $this->assertValidKey($key);

        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $this->assertValidKey($key);

        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $this->assertValidKey($key);

        if ($this->deleteIfExpired($key, $expire)) {
            return true;
        }

        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            try {
                $options = new \Couchbase\GetAndTouchOptions();
                $options = null === $this->timeout ? $options : $options->timeout($this->timeout);
                $this->collection->getAndTouch($key, $expire, $options);

                return true;
            } catch (\Couchbase\Exception\CouchbaseException $e) {
                // SDK >=4.0
                return false;
            } catch (\Couchbase\BaseException $e) {
                // SDK >=3.0 & <4.0
                return false;
            }
        }

        // SDK <3.0
        try {
            $result = $this->collection->getAndTouch($key, $expire);
        } catch (\CouchbaseException $e) {
            return false;
        }

        return !$result->error;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        if ($this->collection instanceof \Couchbase\Collection) {
            // SDK >=3.0
            $bucketSettings = $this->bucketManager->getBucket($this->bucket->name());
            if (!$bucketSettings->flushEnabled()) {
                // `enableFlush` exists, but whether or not it is enabled is config
                // that doesn't belong here; Scrapbook shouldn't alter that
                return false;
            }

            $this->bucketManager->flush($this->bucket->name());

            return true;
        }

        // SDK <3.0
        // depending on config & client version, flush may not be available
        try {
            /*
             * Flush wasn't always properly implemented[1] in the client, plus
             * it depends on server config[2] to be enabled. Return status has
             * been null in both success & failure cases.
             * Flush is a very pervasive function that's likely not called
             * lightly. Since it's probably more important to know whether or
             * not it succeeded, than having it execute as fast as possible, I'm
             * going to add some calls and test if flush succeeded.
             *
             * 1: https://forums.couchbase.com/t/php-flush-isnt-doing-anything/1886/8
             * 2: http://docs.couchbase.com/admin/admin/CLI/CBcli/cbcli-bucket-flush.html
             */
            $this->collection->upsert('cb-flush-tester', '');

            if ($this->collection instanceof \Couchbase\Collection) {
                // SDK >=3.0
                $this->bucketManager->flush($this->bucket->name());
            } elseif (method_exists($this->bucketManager, 'flush')) {
                // SDK >=2.0.6 and <3.0
                $this->bucketManager->flush();
            } elseif (method_exists($this->collection, 'flush')) {
                // SDK <2.0.6
                $this->collection->flush();
            } else {
                return false;
            }
        } catch (\CouchbaseException $e) {
            return false;
        }

        try {
            // cleanup in case flush didn't go through; but if it did, we won't
            // be able to remove it and know flush succeeded
            $result = $this->collection->remove('cb-flush-tester');

            return (bool) $result->error;
        } catch (\CouchbaseException $e) {
            // exception: "The key does not exist on the server"
            return true;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
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
        $this->assertValidKey($key);

        $value = $this->get($key, $token);
        if (false === $value) {
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
     *
     * @param mixed $value
     *
     * @return string|mixed
     */
    protected function serialize($value)
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
     *
     * @param mixed $value
     *
     * @return mixed|int|float
     */
    protected function unserialize($value)
    {
        // more efficient quick check whether value is unserializable
        if (!is_string($value) || !preg_match('/^[saOC]:[0-9]+:/', $value)) {
            return $value;
        }

        $unserialized = @unserialize($value);
        if (false === $unserialized) {
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
     * @param int             $expire
     *
     * @return bool True if expired
     */
    protected function deleteIfExpired($key, $expire)
    {
        if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
            $this->deleteMulti((array) $key);

            return true;
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @throws InvalidKey
     */
    protected function assertValidKey($key)
    {
        if (strlen($key) > 255) {
            throw new InvalidKey("Invalid key: $key. Couchbase keys can not exceed 255 chars.");
        }
    }
}
