<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters\Collections\Utils;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class PrefixReset extends PrefixKeys
{
    protected string $collection;

    public function __construct(KeyValueStore $cache, string $name)
    {
        $this->cache = $cache;
        $this->collection = $name;
        parent::__construct($cache, $this->getPrefix());
    }

    public function flush(): bool
    {
        $index = $this->cache->increment($this->collection);
        $this->setPrefix($this->collection . ':' . $index . ':');

        return $index !== false;
    }

    protected function getPrefix(): string
    {
        /*
         * It's easy enough to just set a prefix to be used, but we can not
         * flush only a prefix!
         * Instead, we'll generate a unique prefix key, based on some name.
         * If we want to flush, we just create a new prefix and use that one.
         */
        $index = $this->cache->get($this->collection);

        if ($index === false) {
            $index = $this->cache->set($this->collection, 1);
        }

        return $this->collection . ':' . $index . ':';
    }
}
