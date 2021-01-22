<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\Exception\UnbegunTransaction;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class TransactionalStoreAdapterTest extends AdapterTest
{
    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = new TransactionalStore($adapter);
    }

    protected function compatSetUp()
    {
        parent::compatSetUp();

        $this->cache->begin();
    }

    protected function compatTearDown()
    {
        parent::compatTearDown();

        try {
            $this->cache->rollback();
        } catch (UnbegunTransaction $e) {
            // this is alright, guess we've terminated the transaction already
        }
    }
}
