<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr16;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class Psr16TestCase extends AdapterTestCase
{
    protected SimpleCache $simplecache;

    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = $adapter;
        $this->simplecache = new SimpleCache($adapter);
    }
}
