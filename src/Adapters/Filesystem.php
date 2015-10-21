<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Filesystem adapter. Data will be written to filesystem, in separate files.
 *
 * @deprecated Use Adapters\Flysystem instead.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Filesystem implements KeyValueStore
{
    /**
     * @var Flysystem
     */
    protected $flysystem;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $adapter = new Local($path, LOCK_EX);
        $filesystem = new FlysystemFilesystem($adapter);
        $this->flysystem = new Flysystem($filesystem);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        return $this->flysystem->get($key, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        return $this->flysystem->getMulti($keys, $tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        return $this->flysystem->set($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        return $this->flysystem->setMulti($items, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return $this->flysystem->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        return $this->flysystem->deleteMulti($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        return $this->flysystem->add($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        return $this->flysystem->replace($key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        return $this->flysystem->cas($token, $key, $value, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        return $this->flysystem->increment($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        return $this->flysystem->decrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        return $this->flysystem->touch($key, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->flysystem->flush();
    }
}
