<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16;

use MatthiasMullie\Scrapbook\Adapters\Collections\Couchbase as CouchbaseCollection;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;

class SimpleCacheTest extends Psr16TestCase
{
    public function testGet()
    {
        // set value in cache directly & test if it can be get from simplecache
        // interface
        $this->cache->set('key', 'value');
        $this->assertSame('value', $this->simplecache->get('key'));
    }

    public function testGetNonExisting()
    {
        $this->assertSame(null, $this->simplecache->get('key'));
    }

    public function testGetDefault()
    {
        $this->assertSame('default', $this->simplecache->get('key', 'default'));
    }

    public function testGetException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->get(array());
    }

    public function testSet()
    {
        $success = $this->simplecache->set('key', 'value');
        $this->assertSame(true, $success);

        // check both cache & simplecache interface to confirm delete
        $this->assertSame('value', $this->cache->get('key'));
        $this->assertSame('value', $this->simplecache->get('key'));
    }

    public function testSetException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->set(5, 5);
    }

    public function testSetExpired()
    {
        $success = $this->simplecache->set('key', 'value', -1);
        $this->assertSame(true, $success);

        $interval = new \DateInterval('PT1S');
        $interval->invert = 1;
        $success = $this->simplecache->set('key2', 'value', $interval);
        $this->assertSame(true, $success);

        // check both cache & simplecache interface to confirm delete
        $this->assertSame(false, $this->cache->get('key'));
        $this->assertSame(null, $this->simplecache->get('key'));
        $this->assertSame(false, $this->cache->get('key2'));
        $this->assertSame(null, $this->simplecache->get('key2'));
    }

    public function testSetFutureExpire()
    {
        $success = $this->simplecache->set('key', 'value', 2);
        $this->assertSame(true, $success);

        $success = $this->simplecache->set('key2', 'value', new \DateInterval('PT2S'));
        $this->assertSame(true, $success);

        sleep(3);

        // Couchbase TTL can't be relied on with 1 second precision = sleep more
        if ($this->cache instanceof Couchbase || $this->cache instanceof CouchbaseCollection) {
            sleep(2);
        }

        // check both cache & simplecache interface to confirm expire
        $this->assertSame(false, $this->cache->get('key'));
        $this->assertSame(null, $this->simplecache->get('key'));
        $this->assertSame(false, $this->cache->get('key2'));
        $this->assertSame(null, $this->simplecache->get('key2'));
    }

    public function testDelete()
    {
        // set value in cache, delete via simplecache interface & confirm it's
        // been deleted
        $this->cache->set('key', 'value');
        $success = $this->simplecache->delete('key');

        // check both cache & simplecache interface to confirm delete
        $this->assertSame(true, $success);
        $this->assertSame(false, $this->cache->get('key'));
        $this->assertSame(null, $this->simplecache->get('key'));
    }

    public function testDeleteException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->delete(new \stdClass());
    }

    public function testClear()
    {
        // some values that should be gone when we clear cache...
        $this->cache->set('key', 'value');
        $this->simplecache->set('key2', 'value');

        $success = $this->simplecache->clear();

        // check both cache & simplecache interface to confirm everything's been
        // wiped out
        $this->assertSame(true, $success);
        $this->assertSame(false, $this->cache->get('key'));
        $this->assertSame(null, $this->simplecache->get('key'));
        $this->assertSame(false, $this->cache->get('key2'));
        $this->assertSame(null, $this->simplecache->get('key2'));
    }

    public function testGetMultiple()
    {
        $this->cache->setMulti(array('key' => 'value', 'key2' => 'value'));
        $results = $this->simplecache->getMultiple(array('key', 'key2', 'key3'));
        $this->assertSame(array('key' => 'value', 'key2' => 'value', 'key3' => null), $results);
    }

    public function testGetMultipleDefault()
    {
        $this->assertSame(array('key' => 'default'), $this->simplecache->getMultiple(array('key'), 'default'));
    }

    public function testGetMultipleTraversable()
    {
        $this->cache->setMulti(array('key' => 'value', 'key2' => 'value'));
        $iterator = new \ArrayIterator(array('key', 'key2', 'key3'));
        $results = $this->simplecache->getMultiple($iterator);
        $this->assertSame(array('key' => 'value', 'key2' => 'value', 'key3' => null), $results);
    }

    public function testGetMultipleException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->getMultiple(null);
    }

    public function testSetMultiple()
    {
        $success = $this->simplecache->setMultiple(array('key' => 'value', 'key2' => 'value'));
        $this->assertSame(true, $success);

        $results = $this->cache->getMulti(array('key', 'key2'));
        $this->assertSame(array('key' => 'value', 'key2' => 'value'), $results);
    }

    public function testSetMultipleTraversable()
    {
        $iterator = new \ArrayIterator(array('key' => 'value', 'key2' => 'value'));
        $success = $this->simplecache->setMultiple($iterator);
        $this->assertSame(true, $success);

        $results = $this->cache->getMulti(array('key', 'key2'));
        $this->assertSame(array('key' => 'value', 'key2' => 'value'), $results);
    }

    public function testSetMultipleException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->setMultiple(123.456);
    }

    public function testDeleteMultiple()
    {
        $this->cache->setMulti(array('key' => 'value', 'key2' => 'value'));
        $success = $this->simplecache->deleteMultiple(array('key', 'key2'));
        $this->assertSame(true, $success);
        $this->assertSame(array(), $this->cache->getMulti(array('key', 'key2')));
    }

    public function testDeleteMultipleTraversable()
    {
        $this->cache->setMulti(array('key' => 'value', 'key2' => 'value'));
        $iterator = new \ArrayIterator(array('key', 'key2'));
        $success = $this->simplecache->deleteMultiple($iterator);
        $this->assertSame(true, $success);
        $this->assertSame(array(), $this->cache->getMulti(array('key', 'key2')));
    }

    public function testDeleteMultipleException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->deleteMultiple(123);
    }

    public function testHas()
    {
        $this->cache->set('key', 'value');

        $this->assertSame(true, $this->simplecache->has('key'));
        $this->assertSame(false, $this->simplecache->has('key2'));
    }

    public function testHasException()
    {
        $this->expectException('Psr\SimpleCache\InvalidArgumentException');
        $this->simplecache->has(true);
    }
}
