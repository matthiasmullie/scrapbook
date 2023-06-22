<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

abstract class AbstractPoolTestCase extends AbstractPsr6TestCase
{
    public function testPoolGetItem(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        // get existing item
        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());

        // get non-existent item
        $item = $this->pool->getItem('key2');
        $this->assertNull($item->get());
    }

    public function testPoolGetItemSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        // get existing item
        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());

        // get non-existent item
        $item = $this->pool->getItem('key2');
        $this->assertNull($item->get());
    }

    public function testPoolGetItems(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        // get existing & non-existent item
        $items = $this->pool->getItems(['key', 'key2']);
        $this->assertEquals('value', $items['key']->get());
        $this->assertNull($items['key2']->get());
    }

    public function testPoolGetItemsSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        // get existing & non-existent item
        $items = $this->pool->getItems(['key', 'key2']);
        $this->assertEquals('value', $items['key']->get());
        $this->assertNull($items['key2']->get());
    }

    public function testPoolHasItem(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        // has existing item
        $this->assertTrue($this->pool->hasItem('key'));

        // has non-existent item
        $this->assertFalse($this->pool->hasItem('key2'));
    }

    public function testPoolHasItemSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        // has existing item
        $this->assertTrue($this->pool->hasItem('key'));

        // has non-existent item
        $this->assertFalse($this->pool->hasItem('key2'));
    }

    public function testPoolClear(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $return = $this->pool->clear();

        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolClearSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        $return = $this->pool->clear();

        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItem(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $return = $this->pool->deleteItem('key');
        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolDeleteNonExistingItem(): void
    {
        $return = $this->pool->deleteItem('key');
        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItemSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        $return = $this->pool->deleteItem('key');
        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItems(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $return = $this->pool->deleteItems(['key']);
        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolDeleteNonExistingItems(): void
    {
        $return = $this->pool->deleteItems(['key']);
        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItemsSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        $return = $this->pool->deleteItems(['key']);
        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertNull($this->pool->getItem('key')->get());
    }

    public function testPoolSave(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $return = $this->pool->save($item);

        $this->assertTrue($return);
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->pool->getItem('key')->get());
    }

    public function testPoolSaveDeferred(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $return = $this->pool->saveDeferred($item);

        $this->assertTrue($return);
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->pool->getItem('key')->get());
    }

    public function testPoolCommit(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $return = $this->pool->commit();

        $this->assertTrue($return);
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->pool->getItem('key')->get());
    }
}
