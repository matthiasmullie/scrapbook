<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class Psr6TestCase extends AdapterTestCase
{
    /**
     * @var Pool
     */
    protected $pool;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->pool = new Pool($adapter);
    }
}
