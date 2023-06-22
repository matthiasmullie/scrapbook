<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\BufferedStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;

abstract class AbstractBufferedStoreTestCase extends AbstractKeyValueStoreTestCase
{
    public function getTestKeyValueStore(KeyValueStore $keyValueStore): KeyValueStore
    {
        return new BufferedStore($keyValueStore);
    }

    public function testBufferedGetFromCache(): void
    {
        // test if value set via buffered cache can be located
        // in buffer & in real cache
        $this->testKeyValueStore->set('key', 'value');
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
    }

    public function testBufferedSetFromCache(): void
    {
        // test if existing value in cache can be fetched from
        // buffer & real cache
        $this->adapterKeyValueStore->set('key', 'value');
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
    }

    public function testBufferedSetFromBuffer(): void
    {
        // test if value that has been set via buffer is actually
        // read from buffer (by deleting it from real cache to make
        // sure it can't be fetched from there)
        $this->testKeyValueStore->set('key', 'value');
        $this->adapterKeyValueStore->delete('key');
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }

    public function testBufferedGetFromBuffer(): void
    {
        // test if value that has been get via buffer is actually
        // read from buffer (by deleting it from real cache to make
        // sure it can't be fetched from there)
        $this->adapterKeyValueStore->set('key', 'value');
        $this->testKeyValueStore->get('key');
        $this->adapterKeyValueStore->delete('key');
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }
}
