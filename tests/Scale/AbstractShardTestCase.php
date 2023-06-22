<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Scale;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Scale\Shard;
use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;

abstract class AbstractShardTestCase extends AbstractKeyValueStoreTestCase
{
    protected KeyValueStore $otherKeyValueStore;

    public function getTestKeyValueStore(KeyValueStore $keyValueStore): KeyValueStore
    {
        $this->otherKeyValueStore = new MemoryStore();

        return new Shard($keyValueStore, $this->otherKeyValueStore);
    }

    public function testShardGet(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals('value1', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->testKeyValueStore->get('key2'));
    }

    public function testShardGetMulti(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertEquals(['key' => 'value1', 'key2' => 'value2'], $this->testKeyValueStore->getMulti(['key', 'key2']));
    }

    public function testShardSet(): void
    {
        $result1 = $this->testKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $result2 = $this->testKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value1', $this->otherKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key2'));
        $this->assertFalse($this->otherKeyValueStore->get('key2'));
    }

    public function testShardSetMulti(): void
    {
        $result = $this->testKeyValueStore->setMulti(['key' => 'value1', 'key2' => 'value2']); // crc32('key') % 2 === 1, crc32('key2') % 2 === 0

        $this->assertEquals(['key' => true, 'key2' => true], $result);

        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value1', $this->otherKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key2'));
        $this->assertFalse($this->otherKeyValueStore->get('key2'));
    }

    public function testShardDelete(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->testKeyValueStore->delete('key');

        $this->assertTrue($result);

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }

    public function testShardDeleteMulti(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->testKeyValueStore->deleteMulti(['key', 'key2']);

        $this->assertEquals(['key' => true, 'key2' => true], $result);

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }

    public function testShardAdd(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1

        $result1 = $this->testKeyValueStore->add('key', 'value1'); // already exists
        $result2 = $this->testKeyValueStore->add('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->assertFalse($result1);
        $this->assertTrue($result2);

        $this->assertEquals('value1', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value1', $this->otherKeyValueStore->get('key'));

        $this->assertEquals('value2', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key2'));
    }

    public function testShardReplace(): void
    {
        // make sure values are spread across the correct shards
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result1 = $this->testKeyValueStore->replace('key', 'changed-value1'); // doesn't yet exist
        $result2 = $this->testKeyValueStore->replace('key2', 'changed-value2');

        $this->assertFalse($result1);
        $this->assertTrue($result2);

        $this->assertEquals('changed-value2', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('changed-value2', $this->adapterKeyValueStore->get('key2'));

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->otherKeyValueStore->get('key'));
    }

    public function testShardCasViaGet(): void
    {
        // make sure values are spread across the correct shards
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->testKeyValueStore->get('key2', $token);

        $this->assertNotNull($token);

        $result = $this->testKeyValueStore->cas($token, 'key2', 'changed-value2');

        $this->assertTrue($result);

        $this->assertEquals('changed-value2', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('changed-value2', $this->adapterKeyValueStore->get('key2'));
    }

    public function testShardCasViaGetMulti(): void
    {
        // make sure values are spread across the correct shards
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $this->testKeyValueStore->getMulti(['key2'], $tokens);

        $this->assertArrayHasKey('key2', $tokens);
        $this->assertNotNull($tokens['key2']);

        $result = $this->testKeyValueStore->cas($tokens['key2'], 'key2', 'changed-value2');

        $this->assertTrue($result);

        $this->assertEquals('changed-value2', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('changed-value2', $this->adapterKeyValueStore->get('key2'));
    }

    public function testShardIncrement(): void
    {
        // make sure values are spread across the correct shards
        $this->adapterKeyValueStore->set('key2', 5); // crc32('key2') % 2 === 0

        $result1 = $this->testKeyValueStore->increment('key', 2, 0);
        $result2 = $this->testKeyValueStore->increment('key2', 2, 0);

        $this->assertEquals(0, $result1);
        $this->assertEquals(7, $result2);

        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->otherKeyValueStore->get('key'));

        $this->assertEquals(7, $this->testKeyValueStore->get('key2'));
        $this->assertEquals(7, $this->adapterKeyValueStore->get('key2'));
    }

    public function testShardDecrement(): void
    {
        // make sure values are spread across the correct shards
        $this->adapterKeyValueStore->set('key2', 5); // crc32('key2') % 2 === 0

        $result1 = $this->testKeyValueStore->decrement('key', 2, 0);
        $result2 = $this->testKeyValueStore->decrement('key2', 2, 0);

        $this->assertEquals(0, $result1);
        $this->assertEquals(3, $result2);

        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->otherKeyValueStore->get('key'));

        $this->assertEquals(3, $this->testKeyValueStore->get('key2'));
        $this->assertEquals(3, $this->adapterKeyValueStore->get('key2'));
    }

    public function testShardTouch(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        // let them expire
        $result1 = $this->testKeyValueStore->touch('key', -2);
        $result2 = $this->testKeyValueStore->touch('key2', -2);

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->otherKeyValueStore->get('key'));

        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }

    public function testShardFlush(): void
    {
        // make sure values are spread across the correct shards
        $this->otherKeyValueStore->set('key', 'value1'); // crc32('key') % 2 === 1
        $this->adapterKeyValueStore->set('key2', 'value2'); // crc32('key2') % 2 === 0

        $result = $this->testKeyValueStore->flush();

        $this->assertTrue($result);

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->otherKeyValueStore->get('key'));

        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }
}
