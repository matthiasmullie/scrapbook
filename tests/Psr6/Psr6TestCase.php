<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class Psr6TestCase extends AdapterProviderTestCase
{
    public function adapterProvider()
    {
        return array_map(function (KeyValueStore $adapter) {
            $pool = new Pool($adapter);

            return array($adapter, $pool);
        }, $this->getAdapters());
    }
}
