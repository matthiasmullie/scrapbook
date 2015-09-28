<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\Exception\ServerUnhealthy;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Couchbase adapter. Basically just a wrapper over \CouchbaseBucket, but in an
 * exchangeable (KeyValueStore) interface.
 *
 * @see http://docs.couchbase.com/developer/php-2.0/php-intro.html
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Couchbase implements KeyValueStore
{
    /**
     * @var \CouchbaseBucket
     */
    protected $client;

    /**
     * @param \CouchbaseBucket $client
     *
     * @throws ServerUnhealthy
     */
    public function __construct(\CouchbaseBucket $client)
    {
        $this->client = $client;

        $info = $this->client->manager()->info();
        foreach ($info['nodes'] as $node) {
            if ($node['status'] !== 'healthy') {
                throw new ServerUnhealthy('Server isn\'t ready yet');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        try {
            $result = $this->client->get($key);
        } catch (\CouchbaseException $e) {
            return false;
        }

        $token = $result->cas;

        return $result->error ? false : $result->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        try {
            $results = $this->client->get($keys);
        } catch (\CouchbaseException $e) {
            return false;
        }

        $tokens = array();
        foreach ($results as $key => $value) {
            if ($value->error) {
                unset($results[$key]);
                continue;
            }

            $results[$key] = $value->value;
            $tokens[$key] = $value->cas;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        try {
            $result = $this->client->upsert($key, $value, array('expiry' => $expire));
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
        foreach ($items as $key => $value) {
            $items[$key] = array(
                'value' => $value,
                'expiry' => $expire,
            );
        }

        try {
            $results = $this->client->upsert($items);
        } catch (\CouchbaseException $e) {
            return false;
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
        try {
            $result = $this->client->remove($key);
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
        try {
            $results = $this->client->remove($keys);
        } catch (\CouchbaseException $e) {
            return false;
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
        try {
            $result = $this->client->insert($key, $value, array('expiry' => $expire));
        } catch (\CouchbaseException $e) {
            return false;
        }

        return !$result->error;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        try {
            $result = $this->client->replace($key, $value, array('expiry' => $expire));
        } catch (\CouchbaseException $e) {
            return false;
        }

        return !$result->error;
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        try {
            $result = $this->client->replace($key, $value, array('expiry' => $expire, 'cas' => $token));
        } catch (\CouchbaseException $e) {
            return false;
        }

        return !$result->error;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        try {
            $result = $this->client->counter($key, $offset, array('initial' => $initial, 'expiry' => $expire));

            /*
             * Negative expire here doesn't properly seem to expire immediately.
             * Unfortunately, we still had to do the counter request to figure
             * out the correct return value.
             */
            if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
                $this->delete($key);
            }
        } catch (\CouchbaseException $e) {
            return false;
        }

        return $result->error ? false : $result->value;
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        try {
            $result = $this->client->counter($key, -$offset, array('initial' => $initial, 'expiry' => $expire));

            /*
             * Negative expire here doesn't properly seem to expire immediately.
             * Unfortunately, we still had to do the counter request to figure
             * out the correct return value.
             */
            if ($expire < 0 || ($expire > 2592000 && $expire < time())) {
                $this->delete($key);
            }
        } catch (\CouchbaseException $e) {
            return false;
        }

        return $result->error ? false : $result->value;
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        if ($expire - time() < 0) {
            return $this->delete($key);
        }

        try {
            $result = $this->client->getAndTouch($key, $expire);
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
        // depending on config & client version, flush may not be available
        try {
            $manager = $this->client->manager();
            if (method_exists($manager, 'flush')) {
                // ext-couchbase >= 2.0.6
                $manager->flush();

                return true;
            } elseif (method_exists($this->client, 'flush')) {
                // ext-couchbase < 2.0.6
                $this->client->flush();

                return true;
            } else {
                return false;
            }
        } catch (\CouchbaseException $e) {
            return false;
        }
    }
}
