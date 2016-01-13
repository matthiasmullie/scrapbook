<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\BufferedStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class BufferedStoreAdapterTest extends AdapterTest
{
    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = new BufferedStore($adapter);
    }
}
