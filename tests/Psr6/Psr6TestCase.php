<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class Psr6TestCase extends AdapterTestCase
{
    protected Pool $pool;

    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = $adapter;
        $this->pool = new Pool($adapter);
    }
}
