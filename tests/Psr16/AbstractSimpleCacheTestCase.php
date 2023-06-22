<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr16;

use MatthiasMullie\Scrapbook\Adapters\Collections\Couchbase as CouchbaseCollection;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AbstractAdapterTestCase;

abstract class AbstractSimpleCacheTestCase extends AbstractAdapterTestCase
{
    protected SimpleCache $simplecache;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simplecache = new SimpleCache($this->adapterKeyValueStore);
        $this->simplecache->clear();
    }

    public function testGet(): void
    {
        // set value in cache directly & test if it can be get from simplecache
        // interface
        $this->adapterKeyValueStore->set('key', 'value');
        $this->assertSame('value', $this->simplecache->get('key'));
    }

    public function testGetNonExisting(): void
    {
        $this->assertNull($this->simplecache->get('key'));
    }

    public function testGetDefault(): void
    {
        $this->assertSame('default', $this->simplecache->get('key', 'default'));
    }

    public function testGetException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->get([]);
    }

    public function testSet(): void
    {
        $success = $this->simplecache->set('key', 'value');
        $this->assertTrue($success);

        // check both cache & simplecache interface to confirm delete
        $this->assertSame('value', $this->adapterKeyValueStore->get('key'));
        $this->assertSame('value', $this->simplecache->get('key'));
    }

    public function testSetException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->set(5, 5);
    }

    public function testSetExpired(): void
    {
        $success = $this->simplecache->set('key', 'value', -1);
        $this->assertTrue($success);

        $interval = new \DateInterval('PT1S');
        $interval->invert = 1;
        $success = $this->simplecache->set('key2', 'value', $interval);
        $this->assertTrue($success);

        // check both cache & simplecache interface to confirm delete
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->simplecache->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
        $this->assertNull($this->simplecache->get('key2'));
    }

    public function testSetFutureExpire(): void
    {
        $success = $this->simplecache->set('key', 'value', 2);
        $this->assertTrue($success);

        $success = $this->simplecache->set('key2', 'value', new \DateInterval('PT2S'));
        $this->assertTrue($success);

        sleep(3);

        // Couchbase TTL can't be relied on with 1 second precision = sleep more
        if ($this->adapterKeyValueStore instanceof Couchbase || $this->adapterKeyValueStore instanceof CouchbaseCollection) {
            sleep(2);
        }

        // check both cache & simplecache interface to confirm expire
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->simplecache->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
        $this->assertNull($this->simplecache->get('key2'));
    }

    public function testDelete(): void
    {
        // set value in cache, delete via simplecache interface & confirm it's
        // been deleted
        $this->adapterKeyValueStore->set('key', 'value');
        $success = $this->simplecache->delete('key');

        // check both cache & simplecache interface to confirm delete
        $this->assertTrue($success);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->simplecache->get('key'));
    }

    public function testDeleteException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->delete(new \stdClass());
    }

    public function testClear(): void
    {
        // some values that should be gone when we clear cache...
        $this->adapterKeyValueStore->set('key', 'value');
        $this->simplecache->set('key2', 'value');

        $success = $this->simplecache->clear();

        // check both cache & simplecache interface to confirm everything's been
        // wiped out
        $this->assertTrue($success);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->simplecache->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
        $this->assertNull($this->simplecache->get('key2'));
    }

    public function testGetMultiple(): void
    {
        $this->adapterKeyValueStore->setMulti(['key' => 'value', 'key2' => 'value']);
        $results = $this->simplecache->getMultiple(['key', 'key2', 'key3']);
        $this->assertSame(['key' => 'value', 'key2' => 'value', 'key3' => null], $results);
    }

    public function testGetMultipleDefault(): void
    {
        $this->assertSame(['key' => 'default'], $this->simplecache->getMultiple(['key'], 'default'));
    }

    public function testGetMultipleTraversable(): void
    {
        $this->adapterKeyValueStore->setMulti(['key' => 'value', 'key2' => 'value']);
        $iterator = new \ArrayIterator(['key', 'key2', 'key3']);
        $results = $this->simplecache->getMultiple($iterator);
        $this->assertSame(['key' => 'value', 'key2' => 'value', 'key3' => null], $results);
    }

    public function testGetMultipleException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->getMultiple(null);
    }

    public function testSetMultiple(): void
    {
        $success = $this->simplecache->setMultiple(['key' => 'value', 'key2' => 'value']);
        $this->assertTrue($success);

        $results = $this->adapterKeyValueStore->getMulti(['key', 'key2']);
        $this->assertSame(['key' => 'value', 'key2' => 'value'], $results);
    }

    public function testSetMultipleTraversable(): void
    {
        $iterator = new \ArrayIterator(['key' => 'value', 'key2' => 'value']);
        $success = $this->simplecache->setMultiple($iterator);
        $this->assertTrue($success);

        $results = $this->adapterKeyValueStore->getMulti(['key', 'key2']);
        $this->assertSame(['key' => 'value', 'key2' => 'value'], $results);
    }

    public function testSetMultipleException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->setMultiple(123.456);
    }

    public function testDeleteMultiple(): void
    {
        $this->adapterKeyValueStore->setMulti(['key' => 'value', 'key2' => 'value']);
        $success = $this->simplecache->deleteMultiple(['key', 'key2']);
        $this->assertTrue($success);
        $this->assertSame([], $this->adapterKeyValueStore->getMulti(['key', 'key2']));
    }

    public function testDeleteMultipleTraversable(): void
    {
        $this->adapterKeyValueStore->setMulti(['key' => 'value', 'key2' => 'value']);
        $iterator = new \ArrayIterator(['key', 'key2']);
        $success = $this->simplecache->deleteMultiple($iterator);
        $this->assertTrue($success);
        $this->assertSame([], $this->adapterKeyValueStore->getMulti(['key', 'key2']));
    }

    public function testDeleteMultipleException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->deleteMultiple(123);
    }

    public function testHas(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $this->assertTrue($this->simplecache->has('key'));
        $this->assertFalse($this->simplecache->has('key2'));
    }

    public function testHasException(): void
    {
        $this->expectException(\TypeError::class);
        $this->simplecache->has(true);
    }
}
