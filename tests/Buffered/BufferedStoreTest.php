<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\BufferedStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class BufferedStoreTest extends AdapterTestCase
{
    /**
     * @var BufferedStore
     */
    protected $buffered;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->buffered = new BufferedStore($adapter);
    }

    public function testGetFromCache()
    {
        // test if value set via buffered cache can be located
        // in buffer & in real cache
        $this->buffered->set('key', 'value');
        $this->assertEquals('value', $this->buffered->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testSetFromCache()
    {
        // test if existing value in cache can be fetched from
        // buffer & real cache
        $this->cache->set('key', 'value');
        $this->assertEquals('value', $this->buffered->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testSetFromBuffer()
    {
        // test if value that has been set via buffer is actually
        // read from buffer (by deleting it from real cache to make
        // sure it can't be fetched from there)
        $this->buffered->set('key', 'value');
        $this->cache->delete('key');
        $this->assertEquals('value', $this->buffered->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testGetFromBuffer()
    {
        // test if value that has been get via buffer is actually
        // read from buffer (by deleting it from real cache to make
        // sure it can't be fetched from there)
        $this->cache->set('key', 'value');
        $this->buffered->get('key');
        $this->cache->delete('key');
        $this->assertEquals('value', $this->buffered->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }
}
