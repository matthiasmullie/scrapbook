<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16;

use DateInterval;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;
use MatthiasMullie\Scrapbook\KeyValueStore;

class SimpleCacheTest extends AdapterTestCase
{
    /**
     * @var SimpleCache
     */
    protected $simplecache;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->simplecache = new SimpleCache($adapter);
    }

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


    public function testSet()
    {
        $success = $this->simplecache->set('key', 'value');
        $this->assertSame(true, $success);

        // check both cache & simplecache interface to confirm delete
        $this->assertSame('value', $this->cache->get('key'));
        $this->assertSame('value', $this->simplecache->get('key'));
    }

    public function testSetExpired()
    {
        $success = $this->simplecache->set('key', 'value', time() - 1);
        $this->assertSame(true, $success);

        $interval = new DateInterval('PT1S');
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
        $success = $this->simplecache->set('key', 'value', time() + 1);
        $this->assertSame(true, $success);

        $success = $this->simplecache->set('key2', 'value', new DateInterval('PT1S'));
        $this->assertSame(true, $success);

        sleep(2);

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
        $this->simplecache->delete('key');

        // check both cache & simplecache interface to confirm delete
        $this->assertSame(false, $this->cache->get('key'));
        $this->assertSame(null, $this->simplecache->get('key'));
    }

    public function testClear()
    {
        // some values that should be gone when we clear cache...
        $this->cache->set('key', 'value');
        $this->simplecache->set('key2', 'value');

        $this->simplecache->clear();

        // check both cache & simplecache interface to confirm everything's been
        // wiped out
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

    public function testSetMultiple()
    {
        $success = $this->simplecache->setMultiple(array('key' => 'value', 'key2' => 'value'));
        $this->assertSame(true, $success);

        $results = $this->cache->getMulti(array('key', 'key2'));
        $this->assertSame(array('key' => 'value', 'key2' => 'value'), $results);
    }

    public function testDeleteMultiple()
    {
        $this->cache->setMulti(array('key' => 'value', 'key2' => 'value'));
        $this->simplecache->deleteMultiple(array('key', 'key2'));
        $this->assertSame(array(), $this->cache->getMulti(array('key', 'key2')));
    }

    public function testExists()
    {
        $this->cache->set('key', 'value');

        $this->assertSame(true, $this->simplecache->exists('key'));
        $this->assertSame(false, $this->simplecache->exists('key2'));
    }

    public function testIncrement()
    {
        // test setting initial value
        $result = $this->simplecache->increment('key', 5);
        $this->assertSame(1, $result);
        $this->assertSame(1, $this->cache->get('key'));

        // test incrementing value
        $result = $this->simplecache->increment('key', 5);
        $this->assertSame(6, $result);
        $this->assertSame(6, $this->cache->get('key'));
    }

    public function testDecrement()
    {
        // test setting initial value
        $result = $this->simplecache->decrement('key', 5);
        $this->assertSame(1, $result);
        $this->assertSame(1, $this->cache->get('key'));

        // test decrementing value
        $result = $this->simplecache->decrement('key', 1);
        $this->assertSame(0, $result);
        $this->assertSame(0, $this->cache->get('key'));

        // decrement again (can't go below 0)
        $result = $this->simplecache->decrement('key', 1);
        $this->assertSame(0, $result);
        $this->assertSame(0, $this->cache->get('key'));
    }
}
