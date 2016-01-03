<?php

namespace MatthiasMullie\Scrapbook\Psr6;

use Cache\Taggable\TaggableItemInterface;
use Cache\Taggable\TaggableItemTrait;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

/**
 * Representation of a cache item, both existing & non-existing (to be created).
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Item implements CacheItemInterface, TaggableItemInterface
{
    use TaggableItemTrait;

    /**
     * @var string
     */
    protected $hash;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @var int
     */
    protected $expire = 0;

    /**
     * @param string     $key
     * @param Repository $repository
     */
    public function __construct($key, Repository $repository)
    {
        $this->key = $this->getKeyFromTaggedKey($key);
        $this->taggedKey = $key;

        /*
         * Register this key (tied to this particular object) to the value
         * repository.
         *
         * If 1 key is requested multiple times, the value could be an object
         * that could be altered (by reference) and if all objects-for-same-key
         * reference that same value, all would've been changed (because all
         * would be that exact same value.)
         *
         * I'm using spl_object_hash to get a unique identifier linking $key to
         * this particular object, without using this object itself (I could use
         * SplObjectStorage.) If I stored this object, it wouldn't be destructed
         * when it's no longer needed, and I want it to destruct so I can free
         * up this value in the repository when it's no longer needed.
         */
        $this->repository = $repository;
        $this->hash = spl_object_hash($this);
        $this->repository->add($this->hash, $this->key);
    }

    /**
     * When this item is being killed, we should no longer keep its value around
     * in the repository. Free up some memory!
     */
    public function __destruct()
    {
        $this->repository->remove($this->hash);
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        // value was already set on this object, return that one!
        if ($this->value !== null) {
            return $this->value;
        }

        // sanity check
        if (!$this->isHit()) {
            return;
        }

        return $this->repository->get($this->hash);
    }

    /**
     * {@inheritdoc}
     */
    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return $this->repository->exists($this->hash);
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        // DateTimeInterface only exists since PHP>=5.5, also accept DateTime
        if ($expiration instanceof DateTimeInterface || $expiration instanceof DateTime) {
            // convert datetime to unix timestamp
            $this->expire = (int) $expiration->format('U');
        } else {
            $this->expire = 0;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        if ($time instanceof DateInterval) {
            $expire = new DateTime();
            $expire->add($time);
            // convert datetime to unix timestamp
            $this->expire = (int) $expire->format('U');
        } elseif (is_int($time)) {
            $this->expire = time() + $time;
        } else {
            throw new InvalidArgumentException(
                'Invalid time: '.serialize($time).'. Must be integer or '.
                'instance of DateInterval.'
            );
        }

        return $this;
    }

    /**
     * Returns the set expiration time in integer form (as it's what
     * KeyValueStore expects).
     *
     * @return int
     */
    public function getExpiration()
    {
        return $this->expire;
    }
}
