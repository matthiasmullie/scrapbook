<?php

namespace MatthiasMullie\Scrapbook\Buffered\Utils;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;

/**
 * This is a helper class for BufferedStore & TransactionalStore, which buffer
 * real cache requests in memory.
 * The memory-part can easily be handled by MemoryStore. There's just 1 gotcha:
 * when an item is to be deleted (but not yet committed), it needs to be deleted
 * from the MemoryStore too, but we need to be able to make a distinction
 * between "this is deleted" and "this value is not known in this memory cache,
 * fall back to real cache".
 *
 * This is where this class comes in to play: we'll add an additional "expired"
 * method, which allows BufferedStore to just expire the keys that are supposed
 * to be deleted (instead of deleting them) - then we can keep track of when
 * a key is just not known, or known-but-deleted (=expired)
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Buffer extends MemoryStore
{
    /**
     * Make items publicly available - if we create a collection from this,
     * that collection will need to be able to access these items to determine
     * if something has expired.
     *
     * @var array
     */
    public $items = array();

    /**
     * Checks if a value exists in cache and is not yet expired.
     * Contrary to default MemoryStore, expired items must *not* be deleted
     * from memory: we need to remember that they were expired, so we don't
     * reach out to real cache (only to get nothing, since it's expired...).
     *
     * @param string $key
     *
     * @return bool
     */
    protected function exists($key)
    {
        if (!array_key_exists($key, $this->items)) {
            // key not in cache
            return false;
        }

        $expire = $this->items[$key][1];
        if ($expire !== 0 && $expire < time()) {
            // not permanent & already expired
            return false;
        }

        $this->lru($key);

        return true;
    }

    /**
     * Check if a key existed in local storage, but is now expired.
     *
     * Because our local buffer is also just a real cache, expired items will
     * just return nothing, which will lead us to believe no such item exists in
     * that local cache, and we'll reach out to the real cache (where the value
     * may not yet have been expired because that may have been part of an
     * uncommitted write)
     * So we'll want to know when a value is in local cache, but expired!
     *
     * @param string $key
     *
     * @return bool
     */
    public function expired($key)
    {
        if ($this->get($key) !== false) {
            // returned a value, clearly not yet expired
            return false;
        }

        // a known item, not returned by get, is expired
        return array_key_exists($key, $this->items);
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection($name)
    {
        return new BufferCollection($this, $name);
    }
}
