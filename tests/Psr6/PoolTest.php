<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

class PoolTest extends Psr6TestCase
{
    public function testPoolGetItem()
    {
        $this->cache->set('key', 'value');

        // get existing item
        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());

        // get non-existent item
        $item = $this->pool->getItem('key2');
        $this->assertEquals(null, $item->get());
    }

    public function testPoolGetItemSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        // get existing item
        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());

        // get non-existent item
        $item = $this->pool->getItem('key2');
        $this->assertEquals(null, $item->get());
    }

    public function testPoolGetItems()
    {
        $this->cache->set('key', 'value');

        // get existing & non-existent item
        $items = $this->pool->getItems(array('key', 'key2'));
        $this->assertEquals('value', $items['key']->get());
        $this->assertEquals(null, $items['key2']->get());
    }

    public function testPoolGetItemsSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        // get existing & non-existent item
        $items = $this->pool->getItems(array('key', 'key2'));
        $this->assertEquals('value', $items['key']->get());
        $this->assertEquals(null, $items['key2']->get());
    }

    public function testPoolHasItem()
    {
        $this->cache->set('key', 'value');

        // has existing item
        $this->assertEquals(true, $this->pool->hasItem('key'));

        // has non-existent item
        $this->assertEquals(false, $this->pool->hasItem('key2'));
    }

    public function testPoolHasItemSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        // has existing item
        $this->assertEquals(true, $this->pool->hasItem('key'));

        // has non-existent item
        $this->assertEquals(false, $this->pool->hasItem('key2'));
    }

    public function testPoolClear()
    {
        $this->cache->set('key', 'value');

        $return = $this->pool->clear();

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolClearSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        $return = $this->pool->clear();

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItem()
    {
        $this->cache->set('key', 'value');

        $return = $this->pool->deleteItem('key');
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolDeleteNonExistingItem()
    {
        $return = $this->pool->deleteItem('key');
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItemSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        $return = $this->pool->deleteItem('key');
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItems()
    {
        $this->cache->set('key', 'value');

        $return = $this->pool->deleteItems(array('key'));
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolDeleteNonExistingItems()
    {
        $return = $this->pool->deleteItems(array('key'));
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolDeleteItemsSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->save($item);

        $return = $this->pool->deleteItems(array('key'));
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(null, $this->pool->getItem('key')->get());
    }

    public function testPoolSave()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $return = $this->pool->save($item);

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('value', $this->pool->getItem('key')->get());
    }

    public function testPoolSaveDeferred()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $return = $this->pool->saveDeferred($item);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals('value', $this->pool->getItem('key')->get());
    }

    public function testPoolCommit()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $return = $this->pool->commit();

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('value', $this->pool->getItem('key')->get());
    }
}
