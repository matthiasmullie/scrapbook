<?php

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Scale\Shard;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class ShardSpreadTest extends AdapterTestCase
{
    /**
     * @var Shard
     */
    protected $shard;

    /**
     * @var KeyValueStore
     */
    protected $other;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->other = new MemoryStore();

        $this->shard = new Shard($this->cache, $this->other);
    }

    public function testGet()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals('value1', $this->shard->get('key'));
        $this->assertEquals('value2', $this->shard->get('key2'));
    }

    public function testGetMulti()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals(array('key' => 'value1', 'key2' => 'value2'), $this->shard->getMulti(array('key', 'key2')));
    }

    public function testSet()
    {
        $result1 = $this->shard->set('key', 'value1'); // crc32('key') % 2 === 1
        $result2 = $this->shard->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals(true, $result1);
        $this->assertEquals(true, $result2);

        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals('value1', $this->other->get('key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals(false, $this->other->get('key2'));
    }

    public function testSetMulti()
    {
        $result = $this->shard->setMulti(array('key' => 'value1', 'key2' => 'value2')); // crc32('key') % 2 === 1, crc32('key2') % 2 === 0

        $this->assertEquals(array('key' => true, 'key2' => true), $result);

        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals('value1', $this->other->get('key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals(false, $this->other->get('key2'));
    }

    public function testDelete()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->shard->delete('key');

        $this->assertEquals(true, $result);

        $this->assertEquals(false, $this->shard->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testDeleteMulti()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->shard->deleteMulti(array('key', 'key2'));

        $this->assertEquals(array('key' => true, 'key2' => true), $result);

        $this->assertEquals(false, $this->shard->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->assertEquals(false, $this->shard->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testAdd()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1

        $result1 = $this->shard->add('key', 'value1'); // already exists
        $result2 = $this->shard->add('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals(false, $result1);
        $this->assertEquals(true, $result2);

        $this->assertEquals('value1', $this->shard->get('key'));
        $this->assertEquals('value1', $this->other->get('key'));

        $this->assertEquals('value2', $this->shard->get('key2'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testReplace()
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result1 = $this->shard->replace('key', 'changed-value1'); // doesn't yet exist
        $result2 = $this->shard->replace('key2', 'changed-value2');

        $this->assertEquals(false, $result1);
        $this->assertEquals(true, $result2);

        $this->assertEquals('changed-value2', $this->shard->get('key2'));
        $this->assertEquals('changed-value2', $this->cache->get('key2'));

        $this->assertEquals(false, $this->shard->get('key'));
        $this->assertEquals(false, $this->other->get('key'));
    }

    public function testCasViaGet()
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->shard->get('key2', $token);

        $this->assertNotNull($token);

        $result = $this->shard->cas($token, 'key2', 'changed-value2');

        $this->assertEquals(true, $result);

        $this->assertEquals('changed-value2', $this->shard->get('key2'));
        $this->assertEquals('changed-value2', $this->cache->get('key2'));
    }

    public function testCasViaGetMulti()
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->shard->getMulti(array('key2'), $tokens);

        $this->assertArrayHasKey('key2', $tokens);
        $this->assertNotNull($tokens['key2']);

        $result = $this->shard->cas($tokens['key2'], 'key2', 'changed-value2');

        $this->assertEquals(true, $result);

        $this->assertEquals('changed-value2', $this->shard->get('key2'));
        $this->assertEquals('changed-value2', $this->cache->get('key2'));
    }

    public function testIncrement()
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 5); // crc32('key2') % 2 === 0

        $result1 = $this->shard->increment('key', 2, 0);
        $result2 = $this->shard->increment('key2', 2, 0);

        $this->assertEquals(0, $result1);
        $this->assertEquals(7, $result2);

        $this->assertEquals(0, $this->shard->get('key'));
        $this->assertEquals(0, $this->other->get('key'));

        $this->assertEquals(7, $this->shard->get('key2'));
        $this->assertEquals(7, $this->cache->get('key2'));
    }

    public function testDecrement()
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 5); // crc32('key2') % 2 === 0

        $result1 = $this->shard->decrement('key', 2, 0);
        $result2 = $this->shard->decrement('key2', 2, 0);

        $this->assertEquals(0, $result1);
        $this->assertEquals(3, $result2);

        $this->assertEquals(0, $this->shard->get('key'));
        $this->assertEquals(0, $this->other->get('key'));

        $this->assertEquals(3, $this->shard->get('key2'));
        $this->assertEquals(3, $this->cache->get('key2'));
    }

    public function testTouch()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        // let them expire
        $result1 = $this->shard->touch('key', -2);
        $result2 = $this->shard->touch('key2', -2);

        $this->assertEquals(true, $result1);
        $this->assertEquals(true, $result2);

        $this->assertEquals(false, $this->shard->get('key'));
        $this->assertEquals(false, $this->other->get('key'));

        $this->assertEquals(false, $this->shard->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testFlush()
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->shard->flush();

        $this->assertEquals(true, $result);

        $this->assertEquals(false, $this->shard->get('key'));
        $this->assertEquals(false, $this->other->get('key'));

        $this->assertEquals(false, $this->shard->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }
}
