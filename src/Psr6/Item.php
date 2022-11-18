<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Psr6;

use Psr\Cache\CacheItemInterface;

/**
 * Representation of a cache item, both existing & non-existing (to be created).
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Item implements CacheItemInterface
{
    protected string $hash;

    protected string $key;

    protected Repository $repository;

    protected mixed $value = null;

    protected int $expire = 0;

    protected bool|null $isHit;

    protected bool $changed = false;

    public function __construct(string $key, Repository $repository)
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

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        // value was already set on this object, return that one!
        if ($this->value !== null) {
            return $this->value;
        }

        // sanity check
        if (!$this->isHit()) {
            return null;
        }

        return $this->repository->get($this->hash);
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->changed = true;

        return $this;
    }

    public function isHit(): bool
    {
        return $this->isHit ?? $this->repository->exists($this->hash);
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->changed = true;

        if ($expiration === null) {
            $this->expire = 0;
        } else {
            // convert datetime to unix timestamp
            $this->expire = (int) $expiration->format('U');
        }

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time instanceof \DateInterval) {
            $expire = new \DateTime();
            $expire->add($time);
            // convert datetime to unix timestamp
            $this->expire = (int) $expire->format('U');
        } elseif (is_int($time)) {
            $this->expire = time() + $time;
        } elseif (is_null($time)) {
            // this is allowed, but just defaults to infinite
            $this->expire = 0;
        } else {
            throw new InvalidArgumentException('Invalid time: ' . serialize($time) . '. Must be integer or instance of DateInterval.');
        }
        $this->changed = true;

        return $this;
    }

    /**
     * Returns the set expiration time in integer form (as it's what
     * KeyValueStore expects).
     */
    public function getExpiration(): int
    {
        return $this->expire;
    }

    /**
     * Returns true if the item is already expired, false otherwise.
     */
    public function isExpired(): bool
    {
        $expire = $this->getExpiration();

        return $expire !== 0 && $expire < time();
    }

    /**
     * We'll want to know if this Item was altered (value or expiration date)
     * once we'll want to store it.
     */
    public function hasChanged(): bool
    {
        return $this->changed;
    }

    /**
     * Allow isHit to be override, in case it's a value that is returned from
     * memory, when a value is being saved deferred.
     */
    public function overrideIsHit(bool $isHit): void
    {
        $this->isHit = $isHit;
    }
}
