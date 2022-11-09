<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\BufferedStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class BufferedStoreAdapterTest extends AdapterTest
{
    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = new BufferedStore($adapter);
    }
}
