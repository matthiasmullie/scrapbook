<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class TransactionalStoreAdapterTest extends AdapterTest
{
    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = new TransactionalStore($adapter);
    }

    public function setUp()
    {
        parent::setUp();

        $this->cache->begin();
    }
}
