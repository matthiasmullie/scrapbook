<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Buffered\Utils;

use MatthiasMullie\Scrapbook\Adapters\Collections\MemoryStore as MemoryStoreCollection;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * A collection implementation for Buffer.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class BufferCollection extends MemoryStoreCollection
{
    /**
     * @var Buffer
     */
    protected KeyValueStore $cache;

    public function __construct(Buffer $cache, string $name)
    {
        parent::__construct($cache, $name);
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
     */
    public function expired(string $key): bool
    {
        if ($this->get($key) !== false) {
            // returned a value, clearly not yet expired
            return false;
        }

        // a known item, not returned by get, is expired
        return array_key_exists($key, $this->cache->items);
    }
}
