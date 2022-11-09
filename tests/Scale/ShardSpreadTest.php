<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Scale\Shard;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class ShardSpreadTest extends AdapterTestCase
{
    protected Shard $shard;

    protected KeyValueStore $other;

    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = $adapter;
        $this->other = new MemoryStore();

        $this->shard = new Shard($this->cache, $this->other);
    }

    public function testGet(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals('value1', $this->shard->get('key'));
        $this->assertEquals('value2', $this->shard->get('key2'));
    }

    public function testGetMulti(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals(['key' => 'value1', 'key2' => 'value2'], $this->shard->getMulti(['key', 'key2']));
    }

    public function testSet(): void
    {
        $result1 = $this->shard->set('key', 'value1'); // crc32('key') % 2 === 1
        $result2 = $this->shard->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        $this->assertFalse($this->cache->get('key'));
        $this->assertEquals('value1', $this->other->get('key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertFalse($this->other->get('key2'));
    }

    public function testSetMulti(): void
    {
        $result = $this->shard->setMulti(['key' => 'value1', 'key2' => 'value2']); // crc32('key') % 2 === 1, crc32('key2') % 2 === 0

        $this->assertEquals(['key' => true, 'key2' => true], $result);

        $this->assertFalse($this->cache->get('key'));
        $this->assertEquals('value1', $this->other->get('key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertFalse($this->other->get('key2'));
    }

    public function testDelete(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->shard->delete('key');

        $this->assertTrue($result);

        $this->assertFalse($this->shard->get('key'));
        $this->assertFalse($this->cache->get('key'));
    }

    public function testDeleteMulti(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->shard->deleteMulti(['key', 'key2']);

        $this->assertEquals(['key' => true, 'key2' => true], $result);

        $this->assertFalse($this->shard->get('key'));
        $this->assertFalse($this->cache->get('key'));

        $this->assertFalse($this->shard->get('key2'));
        $this->assertFalse($this->cache->get('key2'));
    }

    public function testAdd(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1

        $result1 = $this->shard->add('key', 'value1'); // already exists
        $result2 = $this->shard->add('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertFalse($result1);
        $this->assertTrue($result2);

        $this->assertEquals('value1', $this->shard->get('key'));
        $this->assertEquals('value1', $this->other->get('key'));

        $this->assertEquals('value2', $this->shard->get('key2'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testReplace(): void
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result1 = $this->shard->replace('key', 'changed-value1'); // doesn't yet exist
        $result2 = $this->shard->replace('key2', 'changed-value2');

        $this->assertFalse($result1);
        $this->assertTrue($result2);

        $this->assertEquals('changed-value2', $this->shard->get('key2'));
        $this->assertEquals('changed-value2', $this->cache->get('key2'));

        $this->assertFalse($this->shard->get('key'));
        $this->assertFalse($this->other->get('key'));
    }

    public function testCasViaGet(): void
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->shard->get('key2', $token);

        $this->assertNotNull($token);

        $result = $this->shard->cas($token, 'key2', 'changed-value2');

        $this->assertTrue($result);

        $this->assertEquals('changed-value2', $this->shard->get('key2'));
        $this->assertEquals('changed-value2', $this->cache->get('key2'));
    }

    public function testCasViaGetMulti(): void
    {
        // make sure values are spread across the correct shards
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->shard->getMulti(['key2'], $tokens);

        $this->assertArrayHasKey('key2', $tokens);
        $this->assertNotNull($tokens['key2']);

        $result = $this->shard->cas($tokens['key2'], 'key2', 'changed-value2');

        $this->assertTrue($result);

        $this->assertEquals('changed-value2', $this->shard->get('key2'));
        $this->assertEquals('changed-value2', $this->cache->get('key2'));
    }

    public function testIncrement(): void
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

    public function testDecrement(): void
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

    public function testTouch(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        // let them expire
        $result1 = $this->shard->touch('key', -2);
        $result2 = $this->shard->touch('key2', -2);

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        $this->assertFalse($this->shard->get('key'));
        $this->assertFalse($this->other->get('key'));

        $this->assertFalse($this->shard->get('key2'));
        $this->assertFalse($this->cache->get('key2'));
    }

    public function testFlush(): void
    {
        // make sure values are spread across the correct shards
        $this->other->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->cache->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->shard->flush();

        $this->assertTrue($result);

        $this->assertFalse($this->shard->get('key'));
        $this->assertFalse($this->other->get('key'));

        $this->assertFalse($this->shard->get('key2'));
        $this->assertFalse($this->cache->get('key2'));
    }
}
