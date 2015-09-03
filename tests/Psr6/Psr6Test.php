<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use DateTime;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;
use ReflectionObject;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class Psr6Test extends AdapterProviderTestCase
{
    public function adapterProvider()
    {
        parent::adapterProvider();

        return array_map(function(KeyValueStore $adapter) {
            $pool = new Pool($adapter);
            return array($adapter, $pool);
        }, $this->adapters);
    }

    /*
     * Pool
     */

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
    public function testPoolDeleteItems(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $pool->deleteItems(array('key'));
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

        $pool->deleteItems(array('key'));
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
        $pool->save($item);

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
        $pool->saveDeferred($item);

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
        $pool->commit();

        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals('value', $pool->getItem('key')->get());
    }

    /*
     * Item
     */

    /**
     * @dataProvider adapterProvider
     */
    public function testItemGetKey(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $this->assertEquals('key', $item->getKey());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemGetExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');
        $this->assertEquals('value', $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemGetExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');
        $this->assertEquals('value', $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemGetNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $this->assertEquals(null, $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemSetExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemSetExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemSetNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemIsHitExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');
        $this->assertEquals(true, $item->isHit());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemIsHitExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');
        $this->assertEquals(true, $item->isHit());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemIsHitNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $this->assertEquals(false, $item->isHit());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExistsExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');
        $this->assertEquals(true, $item->exists());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExistsExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');
        $this->assertEquals(true, $item->exists());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExistsNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $this->assertEquals(false, $item->exists());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemSetExpirationExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');

        // relative time, both small and large
        $item->setExpiration(5);
        $this->assertEquals(new DateTime('+5 seconds'), $item->getExpiration());
        $item->setExpiration(50 * 24 * 60 * 60);
        $this->assertEquals(new DateTime('+50 days'), $item->getExpiration());

        // DateTime object
        $item->setExpiration(new DateTime('tomorrow'));
        $this->assertEquals(new \DateTime('tomorrow'), $item->getExpiration());

        // permanent
        $item->setExpiration(null);
        $this->assertInstanceOf('\\MatthiasMullie\Scrapbook\\Psr6\\InfinityDateTime', $item->getExpiration());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemSetExpirationExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');

        // relative time, both small and large
        $item->setExpiration(5);
        $this->assertEquals(new DateTime('+5 seconds'), $item->getExpiration());
        $item->setExpiration(50 * 24 * 60 * 60);
        $this->assertEquals(new DateTime('+50 days'), $item->getExpiration());

        // DateTime object
        $item->setExpiration(new DateTime('tomorrow'));
        $this->assertEquals(new \DateTime('tomorrow'), $item->getExpiration());

        // permanent
        $item->setExpiration(null);
        $this->assertInstanceOf('\\MatthiasMullie\Scrapbook\\Psr6\\InfinityDateTime', $item->getExpiration());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemSetExpirationNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');

        // relative time, both small and large
        $item->setExpiration(5);
        $this->assertEquals(new DateTime('+5 seconds'), $item->getExpiration());
        $item->setExpiration(50 * 24 * 60 * 60);
        $this->assertEquals(new DateTime('+50 days'), $item->getExpiration());

        // DateTime object
        $item->setExpiration(new DateTime('tomorrow'));
        $this->assertEquals(new \DateTime('tomorrow'), $item->getExpiration());

        // permanent
        $item->setExpiration(null);
        $this->assertInstanceOf('\\MatthiasMullie\Scrapbook\\Psr6\\InfinityDateTime', $item->getExpiration());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemGetExpiration(KeyValueStore $cache, Pool $pool)
    {
        // pointless, we've just tested this as part of the setExpiration series
    }

    /*
     * Repository
     */

    /**
     * @dataProvider adapterProvider
     */
    public function testDestructUnresolvedItem(KeyValueStore $cache, Pool $pool)
    {
        // add an item
        $item = $pool->getItem('key');

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertNotEmpty($unresolved);
        $this->assertEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDestructResolvedItem(KeyValueStore $cache, Pool $pool)
    {
        // add to cache, so there is something to resolve
        $cache->set('key', 'value');

        // add an item
        $item = $pool->getItem('key');
        $item->get();

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertEmpty($unresolved);
        $this->assertNotEmpty($resolved);

        // item should now get removed from repository
        unset($item);

        list($unresolved, $resolved) = $this->getRepositoryData($pool);
        $this->assertEmpty($unresolved);
        $this->assertEmpty($resolved);
    }

    /**
     * @param Pool $pool
     *
     * @return array
     */
    protected function getRepositoryData(Pool $pool)
    {
        // grab repository from pool
        $object = new ReflectionObject($pool);
        $property = $object->getProperty('repository');
        $property->setAccessible(true);
        $repository = $property->getValue($pool);

        // grab repository queues
        $object = new ReflectionObject($repository);
        $property = $object->getProperty('unresolved');
        $property->setAccessible(true);
        $unresolved = $property->getValue($repository);
        $property = $object->getProperty('resolved');
        $property->setAccessible(true);
        $resolved = $property->getValue($repository);

        return array($unresolved, $resolved);
    }
}
