<?php

namespace MatthiasMullie\Scrapbook\Adapters\Collections\Utils;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class PrefixKeys implements KeyValueStore
{
    /**
     * @var KeyValueStore
     */
    protected $cache;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @param KeyValueStore $cache
     * @param string        $prefix
     */
    public function __construct(KeyValueStore $cache, $prefix)
    {
        $this->cache = $cache;
        $this->setPrefix($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $key = $this->prefix($key);

        return $this->cache->get($key, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $keys = array_map(array($this, 'prefix'), $keys);
        $results = $this->cache->getMulti($keys, $tokens);
        $keys = array_map(array($this, 'unfix'), array_keys($results));
        $tokens = array_combine($keys, $tokens);

        return array_combine($keys, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $key = $this->prefix($key);

        // Note: I have no idea why, but it seems to happen in some cases that
        // `$value` is `null`, but func_get_arg(1) returns the correct value.
        // Makes no sense, probably a very obscure edge case, but it happens.
        // (it didn't seem to happen if `$value` was another variable name...)
        return $this->cache->set($key, func_get_arg(1), $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $keys = array_map(array($this, 'prefix'), array_keys($items));
        $items = array_combine($keys, $items);
        $results = $this->cache->setMulti($items, $expire);
        $keys = array_map(array($this, 'unfix'), array_keys($results));

        return array_combine($keys, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $key = $this->prefix($key);

        return $this->cache->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        $keys = array_map(array($this, 'prefix'), $keys);
        $results = $this->cache->deleteMulti($keys);
        $keys = array_map(array($this, 'unfix'), array_keys($results));

        return array_combine($keys, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $key = $this->prefix($key);

        // Note: I have no idea why, but it seems to happen in some cases that
        // `$value` is `null`, but func_get_arg(1) returns the correct value.
        // Makes no sense, probably a very obscure edge case, but it happens.
        // (it didn't seem to happen if `$value` was another variable name...)
        return $this->cache->add($key, func_get_arg(1), $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $key = $this->prefix($key);

        // Note: I have no idea why, but it seems to happen in some cases that
        // `$value` is `null`, but func_get_arg(1) returns the correct value.
        // Makes no sense, probably a very obscure edge case, but it happens.
        // (it didn't seem to happen if `$value` was another variable name...)
        return $this->cache->replace($key, func_get_arg(1), $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $key = $this->prefix($key);

        // Note: I have no idea why, but it seems to happen in some cases that
        // `$value` is `null`, but func_get_arg(2) returns the correct value.
        // Makes no sense, probably a very obscure edge case, but it happens.
        // (it didn't seem to happen if `$value` was another variable name...)
        return $this->cache->cas($token, $key, func_get_arg(2), $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $key = $this->prefix($key);

        return $this->cache->increment($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $key = $this->prefix($key);

        return $this->cache->decrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $key = $this->prefix($key);

        return $this->cache->touch($key, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->cache->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        return $this->cache->getCollection($name);
    }

    /**
     * @param string $prefix
     */
    protected function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    protected function prefix($key)
    {
        return $this->prefix.$key;
    }

    /**
     * {@inheritdoc}
     */
    protected function unfix($key)
    {
        return preg_replace('/^'.preg_quote($this->prefix, '/').'/', '', $key);
    }
}
