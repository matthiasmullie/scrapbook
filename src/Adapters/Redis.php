<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\Adapters\Collections\Redis as Collection;
use MatthiasMullie\Scrapbook\Exception\InvalidCollection;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Redis adapter. Basically just a wrapper over \Redis, but in an exchangeable
 * (KeyValueStore) interface.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Redis implements KeyValueStore
{
    /**
     * @var \Redis
     */
    protected $client;

    /**
     * @var string|null
     */
    protected $version;

    /**
     * @param \Redis $client
     */
    public function __construct(\Redis $client)
    {
        $this->client = $client;

        // set a serializer if none is set already
        if ($this->client->getOption(\Redis::OPT_SERIALIZER) == \Redis::SERIALIZER_NONE) {
            $this->client->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $this->client->multi();

        $this->client->get($key);
        $this->client->exists($key);

        /** @var array $return */
        $return = $this->client->exec();
        if ($return === false) {
            return false;
        }

        $value = $return[0];
        $exists = $return[1];

        // no value = quit early, don't generate a useless token
        if (!$exists) {
            $token = null;

            return false;
        }

        // serializing to make sure we don't pass objects (by-reference) ;)
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

        $this->client->multi();

        $this->client->mget($keys);
        foreach ($keys as $key) {
            $this->client->exists($key);
        }

        /** @var array $return */
        $return = $this->client->exec();
        if ($return === false) {
            return array();
        }

        $values = array_shift($return);
        $exists = $return;

        if ($values === false) {
            $values = array_fill_keys($keys, false);
        }
        $values = array_combine($keys, $values);
        $exists = array_combine($keys, $exists);

        $tokens = array();
        foreach ($values as $key => $value) {
            // filter out non-existing values
            if ($exists[$key] === false) {
                unset($values[$key]);
                continue;
            }

            // serializing to make sure we don't pass objects (by-reference) ;)
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

        /*
         * Negative ttl behavior isn't properly documented & doesn't always
         * appear to treat the value as non-existing. Let's play safe and just
         * delete it right away!
         */
        if ($ttl < 0) {
            $this->delete($key);

            return true;
        }

        /*
         * phpredis advises: "Calling setex() is preferred if you want a Time To
         * Live". It seems that setex it what set will fall back to if you pass
         * it a TTL anyway.
         * Redis advises: "Note: Since the SET command options can replace
         * SETNX, SETEX, PSETEX, it is possible that in future versions of Redis
         * these three commands will be deprecated and finally removed."
         * I'll just go with set() - it works and seems the desired path for the
         * future.
         *
         * @see https://github.com/ukko/phpredis-phpdoc/blob/master/src/Redis.php#L190
         * @see https://github.com/phpredis/phpredis#set
         * @see http://redis.io/commands/SET
         */
        return $this->client->set($key, $value, $ttl);
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

        /*
         * Negative ttl behavior isn't properly documented & doesn't always
         * appear to treat the value as non-existing. Let's play safe and just
         * delete it right away!
         */
        if ($ttl < 0) {
            $this->deleteMulti(array_keys($items));

            return array_fill_keys(array_keys($items), true);
        }

        if ($ttl === null) {
            $success = $this->client->mset($items);

            return array_fill_keys(array_keys($items), $success);
        }

        $this->client->multi();
        $this->client->mset($items);

        // Redis has no convenient multi-expire method
        foreach ($items as $key => $value) {
            $this->client->expire($key, $ttl);
        }

        /* @var bool[] $return */
        $result = (array) $this->client->exec();

        $return = array();
        $keys = array_keys($items);
        $success = array_shift($result);
        foreach ($result as $i => $value) {
            $key = $keys[$i];
            $return[$key] = $success && $value;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return (bool) $this->client->del($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        if (empty($keys)) {
            return array();
        }

        /*
         * del will only return the amount of deleted entries, but we also want
         * to know which failed. Deletes will only fail for items that don't
         * exist, so we'll just ask for those and see which are missing.
         */
        $items = $this->getMulti($keys);

        $this->client->del($keys);

        $return = array();
        foreach ($keys as $key) {
            $return[$key] = array_key_exists($key, $items);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $ttl = $this->ttl($expire);

        /*
         * Negative ttl behavior isn't properly documented & doesn't always
         * appear to treat the value as non-existing. Let's play safe and not
         * even create the value (also saving a request)
         */
        if ($ttl < 0) {
            return true;
        }

        if ($ttl === null) {
            return $this->client->setnx($key, $value);
        }

        /*
         * I could use Redis 2.6.12-style options array:
         * $this->client->set($key, $value, array('xx', 'ex' => $ttl));
         * However, this one should be pretty fast already, compared to the
         * replace-workaround below.
         */
        $this->client->multi();
        $this->client->setnx($key, $value);
        $this->client->expire($key, $ttl);

        /** @var bool[] $return */
        $return = (array) $this->client->exec();

        return !in_array(false, $return);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $ttl = $this->ttl($expire);

        /*
         * Negative ttl behavior isn't properly documented & doesn't always
         * appear to treat the value as non-existing. Let's play safe and just
         * delete it right away!
         */
        if ($ttl < 0) {
            return $this->delete($key);
        }

        /*
         * Redis supports passing set() an extended options array since >=2.6.12
         * which allows for an easy and 1-request way to replace a value.
         * That version already comes with Ubuntu 14.04. Ubuntu 12.04 (still
         * widely used and in LTS) comes with an older version, however, so I
         * want to support that too.
         * Supporting both versions comes at a cost.
         * I'll optimize for recent versions, which will get (in case of replace
         * failure) 1 additional network request (for version info). Older
         * versions will get 2 additional network requests: a failed replace
         * (because the options are unknown) & a version check.
         */
        if ($this->version === null || $this->supportsOptionsArray()) {
            $options = array('xx');
            if ($ttl > 0) {
                /*
                 * Not adding 0 TTL to options:
                 * * HHVM (used to) interpret(s) wrongly & throw an exception
                 * * it's not needed anyway, for 0...
                 * @see https://github.com/facebook/hhvm/pull/4833
                 */
                $options['ex'] = $ttl;
            }

            // either we support options array or we haven't yet checked, in
            // which case I'll assume a recent server is running
            $result = $this->client->set($key, $value, $options);
            if ($result !== false) {
                return $result;
            }

            if ($this->supportsOptionsArray()) {
                // failed execution, but not because our Redis version is too old
                return false;
            }
        }

        // workaround for old Redis versions
        $this->client->watch($key);

        $exists = $this->client->exists('key');
        if (!$exists) {
            /*
             * HHVM Redis only got unwatch recently
             * @see https://github.com/asgrim/hhvm/commit/bf5a259cece5df8a7617133c85043608d1ad5316
             */
            if (method_exists($this->client, 'unwatch')) {
                $this->client->unwatch();
            } else {
                // this should also kill the watch...
                $this->client->multi()->discard();
            }

            return false;
        }

        // since we're watching the key, this will fail should it change in the
        // meantime
        $this->client->multi();

        $this->client->set($key, $value, $ttl);

        /** @var bool[] $return */
        $return = (array) $this->client->exec();

        return !in_array(false, $return);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $this->client->watch($key);

        // check if the value still matches CAS token
        $comparison = $this->client->get($key);
        if (serialize($comparison) !== $token) {
            /*
             * HHVM Redis only got unwatch recently
             * @see https://github.com/asgrim/hhvm/commit/bf5a259cece5df8a7617133c85043608d1ad5316
             */
            if (method_exists($this->client, 'unwatch')) {
                $this->client->unwatch();
            } else {
                // this should also kill the watch...
                $this->client->multi()->discard();
            }

            return false;
        }

        $ttl = $this->ttl($expire);

        // since we're watching the key, this will fail should it change in the
        // meantime
        $this->client->multi();

        /*
         * Negative ttl behavior isn't properly documented & doesn't always
         * appear to treat the value as non-existing. Let's play safe and just
         * delete it right away!
         */
        if ($ttl < 0) {
            $this->client->del($key);
        } else {
            $this->client->set($key, $value, $ttl);
        }

        /** @var bool[] $return */
        $return = (array) $this->client->exec();

        return !in_array(false, $return);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        // INCRBY initializes (at 0) & immediately increments, whereas we
        // only do initialization if the value does not yet exist
        if ($initial + $offset === 0 && $expire === 0) {
            return $this->client->incrBy($key, $offset);
        }

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

        // DECRBY can't be used. Not even if we don't need an initial
        // value (it auto-initializes at 0) or expire. Problem is it
        // will decrement below 0, which is something we don't support.

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $ttl = $this->ttl($expire);

        if ($ttl < 0) {
            // Redis can't set expired, so just remove in that case ;)
            return (bool) $this->client->del($key);
        }

        return $this->client->expire($key, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->client->flushAll();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        if (!is_numeric($name)) {
            throw new InvalidCollection(
                'Redis database names must be numeric. '.serialize($name).' given.'
            );
        }

        // we can't reuse $this->client in a different object, because it'll
        // operate on a different database
        $client = new \Redis();

        if ($this->client->getPersistentID() !== null) {
            $client->pconnect(
                $this->client->getHost(),
                $this->client->getPort(),
                $this->client->getTimeout()
            );
        } else {
            $client->connect(
                $this->client->getHost(),
                $this->client->getPort(),
                $this->client->getTimeout()
            );
        }

        $auth = $this->client->getAuth();
        if ($auth !== null) {
            $client->auth($auth);
        }

        $readTimeout = $this->client->getReadTimeout();
        if ($readTimeout) {
            $client->setOption(\Redis::OPT_READ_TIMEOUT, $this->client->getReadTimeout());
        }

        return new Collection($client, $name);
    }

    /**
     * Redis expects true TTL, not expiration timestamp.
     *
     * @param int $expire
     *
     * @return int|null TTL in seconds, or `null` for no expiration
     */
    protected function ttl($expire)
    {
        if ($expire === 0) {
            return null;
        }

        // relative time in seconds, <30 days
        if ($expire > 30 * 24 * 60 * 60) {
            return $expire - time();
        }

        return $expire;
    }

    /**
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * & use of non-ttl native methods split up.
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

        $this->client->watch($key);

        $value = $this->client->get($key);

        if ($value === false) {
            /*
             * Negative ttl behavior isn't properly documented & doesn't always
             * appear to treat the value as non-existing. Let's play safe and not
             * even create the value (also saving a request)
             */
            if ($ttl < 0) {
                return true;
            }

            // value is not yet set, store initial value!
            $this->client->multi();
            $this->client->set($key, $initial, $ttl);

            /** @var bool[] $return */
            $return = (array) $this->client->exec();

            return !in_array(false, $return) ? $initial : false;
        }

        // can't increment if a non-numeric value is set
        if (!is_numeric($value) || $value < 0) {
            /*
             * HHVM Redis only got unwatch recently.
             * @see https://github.com/asgrim/hhvm/commit/bf5a259cece5df8a7617133c85043608d1ad5316
             */
            if (method_exists($this->client, 'unwatch')) {
                $this->client->unwatch();
            } else {
                // this should also kill the watch...
                $this->client->multi()->discard();
            }

            return false;
        }

        $value += $offset;
        // value can never be lower than 0
        $value = max(0, $value);

        $this->client->multi();

        /*
         * Negative ttl behavior isn't properly documented & doesn't always
         * appear to treat the value as non-existing. Let's play safe and just
         * delete it right away!
         */
        if ($ttl < 0) {
            $this->client->del($key);
        } else {
            $this->client->set($key, $value, $ttl);
        }

        /** @var bool[] $return */
        $return = (array) $this->client->exec();

        return !in_array(false, $return) ? $value : false;
    }

    /**
     * Returns the version of the Redis server we're connecting to.
     *
     * @return string
     */
    protected function getVersion()
    {
        if ($this->version === null) {
            $info = $this->client->info();
            $this->version = $info['redis_version'];
        }

        return $this->version;
    }

    /**
     * Version-based check to test if passing an options array to set() is
     * supported.
     *
     * @return bool
     */
    protected function supportsOptionsArray()
    {
        return version_compare($this->getVersion(), '2.6.12') >= 0;
    }
}
