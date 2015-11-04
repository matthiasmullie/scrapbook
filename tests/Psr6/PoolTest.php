<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class PoolTest extends Psr6TestCase
{
    /**
     * @dataProvider adapterProvider
     */
    public function testPoolGetItem(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        // get existing item
        $item = $pool->getItem('key');
        $this->assertEquals('value', $item->get());

        // get non-existent item
        $item = $pool->getItem('key2');
        $this->assertEquals(null, $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolGetItemSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->save($item);

        // get existing item
        $item = $pool->getItem('key');
        $this->assertEquals('value', $item->get());

        // get non-existent item
        $item = $pool->getItem('key2');
        $this->assertEquals(null, $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolGetItems(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        // get existing & non-existent item
        $items = $pool->getItems(array('key', 'key2'));
        $this->assertEquals('value', $items['key']->get());
        $this->assertEquals(null, $items['key2']->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolGetItemsSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->save($item);

        // get existing & non-existent item
        $items = $pool->getItems(array('key', 'key2'));
        $this->assertEquals('value', $items['key']->get());
        $this->assertEquals(null, $items['key2']->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolHasItem(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        // has existing item
        $this->assertEquals(true, $pool->hasItem('key'));

        // has non-existent item
        $this->assertEquals(false, $pool->hasItem('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolHasItemSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->save($item);

        // has existing item
        $this->assertEquals(true, $pool->hasItem('key'));

        // has non-existent item
        $this->assertEquals(false, $pool->hasItem('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolClear(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $return = $pool->clear();

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(null, $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolClearSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->save($item);

        $return = $pool->clear();

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(null, $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolDeleteItem(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $return = $pool->deleteItem('key');
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(null, $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolDeleteItemSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->save($item);

        $return = $pool->deleteItem('key');
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(null, $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolDeleteItems(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $return = $pool->deleteItems(array('key'));
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(null, $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolDeleteItemsSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->save($item);

        $return = $pool->deleteItems(array('key'));
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(null, $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolSave(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $return = $pool->save($item);

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals('value', $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolSaveDeferred(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $return = $pool->saveDeferred($item);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals('value', $pool->getItem('key')->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testPoolCommit(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $return = $pool->commit();

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals('value', $pool->getItem('key')->get());
    }
}
