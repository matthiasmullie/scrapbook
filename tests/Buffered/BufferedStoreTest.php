<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\BufferedStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class BufferedStoreTest extends AdapterTest
{
    public function adapterProvider()
    {
        parent::adapterProvider();

        // make BufferedStore objects for all adapters & run
        // the regular test suite again
        return array_map(function(KeyValueStore $adapter) {
            return array(new BufferedStore($adapter));
        }, $this->adapters);
    }
}
