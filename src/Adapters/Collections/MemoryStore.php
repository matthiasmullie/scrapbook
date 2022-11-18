<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use MatthiasMullie\Scrapbook\Adapters\Collections\Utils\PrefixKeys;
use MatthiasMullie\Scrapbook\Adapters\MemoryStore as Adapter;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * MemoryStore adapter for a subset of data.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class MemoryStore extends PrefixKeys
{
    /**
     * @var Adapter
     */
    protected KeyValueStore $cache;

    public function __construct(Adapter $cache, string $name)
    {
        parent::__construct($cache, $name . ':');
    }

    public function flush(): bool
    {
        foreach ($this->cache->items as $key => $value) {
            if (str_starts_with($key, $this->prefix)) {
                $this->cache->delete($key);
            }
        }

        return true;
    }
}
