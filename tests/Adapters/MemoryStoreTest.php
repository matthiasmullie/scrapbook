<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use PHPUnit\Framework\TestCase;

/**
 * @group default
 * @group MemoryStore
 */
class MemoryStoreTest extends TestCase
{
    public function testLRU()
    {
        // all of the below 'valueX' have a (serialized) size of 13b
        $limit = strlen(serialize('value1')) + strlen(serialize('value2'));
        // cache should be able to handle only 2 of the below values at once
        $cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore($limit);

        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));

        $cache->set('key3', 'value3');
        $cache->set('key1', 'value4');

        $this->assertEquals('value4', $cache->get('key1'));
        $this->assertEquals(false, $cache->get('key2'));
        $this->assertEquals('value3', $cache->get('key3'));
    }

    public function testLRUNoDoubleCount()
    {
        // all of the below 'valueX' have a (serialized) size of 13b
        $limit = strlen(serialize('value1')) + strlen(serialize('value2'));
        // cache should be able to handle only 2 of the below values at once
        $cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore($limit);

        // writing to same key more than once should not double-count it
        $cache->set('key1', 'value1');
        $cache->set('key1', 'value2');
        $cache->set('key2', 'value3');

        $this->assertEquals('value2', $cache->get('key1'));
        $this->assertEquals('value3', $cache->get('key2'));
    }

    public function testLRUInParentAfterParentSet()
    {
        // all of the below 'valueX' have a (serialized) size of 13b
        $limit = strlen(serialize('value1')) + strlen(serialize('value2'));
        // cache should be able to handle only 2 of the below values at once
        $cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore($limit);
        $collection = $cache->getCollection('collection');

        $cache->set('key1', 'value1');
        $collection->set('key2', 'value2');

        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $collection->get('key2'));

        $cache->set('key3', 'value3');

        $this->assertEquals(false, $cache->get('key1'));
        $this->assertEquals('value2', $collection->get('key2'));
        $this->assertEquals('value3', $cache->get('key3'));
    }

    public function testLRUInCollectionAfterParentSet()
    {
        // all of the below 'valueX' have a (serialized) size of 13b
        $limit = strlen(serialize('value1')) + strlen(serialize('value2'));
        // cache should be able to handle only 2 of the below values at once
        $cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore($limit);
        $collection = $cache->getCollection('collection');

        $collection->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $this->assertEquals('value1', $collection->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));

        $cache->set('key3', 'value3');

        $this->assertEquals(false, $collection->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));
        $this->assertEquals('value3', $cache->get('key3'));
    }

    public function testLRUInParentAfterCollectionSet()
    {
        // all of the below 'valueX' have a (serialized) size of 13b
        $limit = strlen(serialize('value1')) + strlen(serialize('value2'));
        // cache should be able to handle only 2 of the below values at once
        $cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore($limit);
        $collection = $cache->getCollection('collection');

        $cache->set('key1', 'value1');
        $collection->set('key2', 'value2');

        $this->assertEquals('value1', $cache->get('key1'));
        $this->assertEquals('value2', $collection->get('key2'));

        $collection->set('key3', 'value3');

        $this->assertEquals(false, $cache->get('key1'));
        $this->assertEquals('value2', $collection->get('key2'));
        $this->assertEquals('value3', $collection->get('key3'));
    }

    public function testLRUInCollectionAfterCollectionSet()
    {
        // all of the below 'valueX' have a (serialized) size of 13b
        $limit = strlen(serialize('value1')) + strlen(serialize('value2'));
        // cache should be able to handle only 2 of the below values at once
        $cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore($limit);
        $collection = $cache->getCollection('collection');

        $collection->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $this->assertEquals('value1', $collection->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));

        $collection->set('key3', 'value3');

        $this->assertEquals(false, $collection->get('key1'));
        $this->assertEquals('value2', $cache->get('key2'));
        $this->assertEquals('value3', $collection->get('key3'));
    }
}
