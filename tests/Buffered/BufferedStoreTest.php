<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
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
        return array_map(function (KeyValueStore $adapter) {
            return array(new BufferedStore($adapter));
        }, $this->adapters);
    }

    public function testGetFromCache()
    {
        $cache = new MemoryStore();
        $buffered = new BufferedStore($cache);

        // test if value set via buffered cache can be located
        // in buffer & in real cache
        $buffered->set('key', 'value');
        $this->assertEquals('value', $buffered->get('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    public function testSetFromCache()
    {
        $cache = new MemoryStore();
        $buffered = new BufferedStore($cache);

        // test if existing value in cache can be fetched from
        // buffer & real cache
        $cache->set('key', 'value');
        $this->assertEquals('value', $buffered->get('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    public function testSetFromBuffer()
    {
        $cache = new MemoryStore();
        $buffered = new BufferedStore($cache);

        // test if value that has been set via buffer is actually
        // read from buffer (by deleting it from real cache to make
        // sure it can't be fetched from there)
        $buffered->set('key', 'value');
        $cache->delete('key');
        $this->assertEquals('value', $buffered->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    public function testGetFromBuffer()
    {
        $cache = new MemoryStore();
        $buffered = new BufferedStore($cache);

        // test if value that has been get via buffer is actually
        // read from buffer (by deleting it from real cache to make
        // sure it can't be fetched from there)
        $cache->set('key', 'value');
        $buffered->get('key');
        $cache->delete('key');
        $this->assertEquals('value', $buffered->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }
}
