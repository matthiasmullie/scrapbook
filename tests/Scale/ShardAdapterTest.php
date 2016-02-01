<?php

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Scale\Shard;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class ShardAdapterTest extends AdapterTest
{
    public function setAdapter(KeyValueStore $adapter)
    {
        $other = new MemoryStore();

        $this->cache = new Shard($adapter, $other);
    }
}
