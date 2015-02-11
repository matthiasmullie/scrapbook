<?php
namespace Scrapbook\Adapters\Tests;

use Scrapbook\Adapters\MemoryStore;
use Scrapbook\Cache\Tests\KeyValueStoreTestCase;

class MemoryStoreTest extends KeyValueStoreTestCase
{
    /**
     * @return MemoryStore
     */
    protected function getStore()
    {
        return new MemoryStore();
    }
}
