<?php

namespace MatthiasMullie\Scrapbook\Psr6;

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
class Item implements CacheItemInterface
{
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
     * @var DateTimeInterface|DateTime|null
     */
    protected $expire;

    /**
     * @param string     $key
     * @param Repository $repository
     */
    public function __construct($key, Repository $repository)
    {
        $this->key = $key;

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
    public function exists()
    {
        return $this->isHit();
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        // DateTimeInterface only exists since PHP>=5.5, also accept DateTime
        if ($expiration instanceof DateTimeInterface || $expiration instanceof DateTime) {
            $this->expire = $expiration;
        } else {
            $this->expire = null;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        if ($time instanceof DateInterval) {
            $this->expire = new DateTime();
            $this->expire->add($time);
        } elseif (is_int($time)) {
            $this->expire = new DateTime("+$time seconds");
        } else {
            throw new InvalidArgumentException(
                'Invalid time: '.serialize($time).'. Must be integer or '.
                'instance of DateInterval.'
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiration()
    {
        if (!$this->expire) {
            /*
             * Since it's impossible to represent "infinity" in DateTime (for
             * permanent cache storage), I'm using a stub class to represent it.
             * Meanwhile, the real value it holds will be 100 billion dollars,
             * eh years! Long enough to not cause confusion ;)
             */
            return new InfinityDateTime('+100000000000 years');
        }

        return $this->expire;
    }
}
