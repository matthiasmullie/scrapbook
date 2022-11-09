<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\Exception\UnbegunTransaction;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class TransactionalStoreAdapterTest extends AdapterTest
{
    /**
     * @var TransactionalStore
     */
    protected KeyValueStore $cache;

    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = new TransactionalStore($adapter);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache->begin();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        try {
            $this->cache->rollback();
        } catch (UnbegunTransaction $e) {
            // this is alright, guess we've terminated the transaction already
        }
    }
}
