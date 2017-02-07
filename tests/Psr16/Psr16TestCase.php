<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class Psr16TestCase extends AdapterTestCase
{
    /**
     * @var SimpleCache
     */
    protected $simplecache;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->simplecache = new SimpleCache($adapter);
    }
}
