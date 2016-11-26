<?php

namespace MatthiasMullie\Scrapbook\Psr6;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Helper object to serve as glue between pool & item.
 *
 * New items are created by first get()-ing them from the pool. It would be
 * inefficient to let such a get() immediately query the real cache (because it
 * may not be meant to retrieve real data, but just to set a new value)
 *
 * Instead, every Item returned by get() will be a "placeholder", and once the
 * values are actually needed, this object will be called to go do that (along
 * with every other value that has not yet been resolved, while we're at it)
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Repository
{
    /**
     * @var KeyValueStore
     */
    protected $store;

    /**
     * Array of resolved items.
     *
     * @var mixed[] [unique => value]
     */
    protected $resolved = array();

    /**
     * Array of unresolved items.
     *
     * @var string[] [unique => key]
     */
    protected $unresolved = array();

    /**
     * @param KeyValueStore $store
     */
    public function __construct(KeyValueStore $store)
    {
        $this->store = $store;
    }

    /**
     * Add a to-be-resolved cache key.
     *
     * @param string $unique
     * @param string $key
     */
    public function add($unique, $key)
    {
        $this->unresolved[$unique] = $key;
    }

    /**
     * This repository holds the real values for all Item objects. However, if
     * such an item gets garbage collected, there is no point in wasting any
     * more memory storing that value.
     * In that case, this method can be called to remove those values.
     *
     * @param string $unique
     */
    public function remove($unique)
    {
        unset($this->unresolved[$unique], $this->resolved[$unique]);
    }

    /**
     * @param string $unique
     *
     * @return mixed|null Value, of null if non-existent
     */
    public function get($unique)
    {
        return $this->exists($unique) ? $this->resolved[$unique] : null;
    }

    /**
     * @param string $unique
     *
     * @return bool
     */
    public function exists($unique)
    {
        if (array_key_exists($unique, $this->unresolved)) {
            $this->resolve();
        }

        return array_key_exists($unique, $this->resolved);
    }

    /**
     * Resolve all unresolved keys at once.
     */
    protected function resolve()
    {
        $keys = array_unique(array_values($this->unresolved));
        $values = $this->store->getMulti($keys);

        foreach ($this->unresolved as $unique => $key) {
            if (!array_key_exists($key, $values)) {
                // key doesn't exist in cache
                continue;
            }

            /*
             * In theory, there could've been multiple unresolved requests for
             * the same cache key. In the case of objects, we'll clone them
             * to make sure that when the value for 1 item is manipulated, it
             * doesn't affect the value of the other item (because those objects
             * would be passed by-ref without the cloning)
             */
            $value = $values[$key];
            $value = is_object($value) ? clone $value : $value;

            $this->resolved[$unique] = $value;
        }

        $this->unresolved = array();
    }
}
