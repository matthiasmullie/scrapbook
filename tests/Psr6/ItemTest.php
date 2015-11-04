<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class ItemTest extends Psr6TestCase
{
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
    public function testItemExpiresAtExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEquals(strtotime('tomorrow'), $item->getExpiration(), '', 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExpiresAtExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEquals(strtotime('tomorrow'), $item->getExpiration(), '', 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExpiresAtNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEquals(strtotime('tomorrow'), $item->getExpiration(), '', 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExpiresAfterExisting(KeyValueStore $cache, Pool $pool)
    {
        $cache->set('key', 'value');

        $item = $pool->getItem('key');

        // relative time, both small and large
        $item->expiresAfter(5);
        $this->assertEquals(strtotime('+5 seconds'), $item->getExpiration(), '', 1);
        $item->expiresAfter(50 * 24 * 60 * 60);
        $this->assertEquals(strtotime('+50 days'), $item->getExpiration(), '', 1);

        // DateInterval object
        $item->expiresAfter(new \DateInterval('PT5S'));
        $this->assertEquals(strtotime('+5 seconds'), $item->getExpiration(), '', 1);
        $item->expiresAfter(new \DateInterval('P50D'));
        $this->assertEquals(strtotime('+50 days'), $item->getExpiration(), '', 1);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExpiresAfterExistingSetViaPool(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');
        $item->set('value');
        $pool->saveDeferred($item);
        $pool->commit();

        $item = $pool->getItem('key');

        // relative time, both small and large
        $item->expiresAfter(5);
        $this->assertEquals(strtotime('+5 seconds'), $item->getExpiration(), '', 1);
        $item->expiresAfter(50 * 24 * 60 * 60);
        $this->assertEquals(strtotime('+50 days'), $item->getExpiration(), '', 1);

        // DateInterval object
        $item->expiresAfter(new \DateInterval('PT5S'));
        $this->assertEquals(strtotime('+5 seconds'), $item->getExpiration(), '', 1);
        $item->expiresAfter(new \DateInterval('P50D'));
        $this->assertEquals(strtotime('+50 days'), $item->getExpiration(), '', 1);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemExpiresAfterNonExisting(KeyValueStore $cache, Pool $pool)
    {
        $item = $pool->getItem('key');

        // relative time, both small and large
        $item->expiresAfter(5);
        $this->assertEquals(strtotime('+5 seconds'), $item->getExpiration(), '', 1);
        $item->expiresAfter(50 * 24 * 60 * 60);
        $this->assertEquals(strtotime('+50 days'), $item->getExpiration(), '', 1);

        // DateInterval object
        $item->expiresAfter(new \DateInterval('PT5S'));
        $this->assertEquals(strtotime('+5 seconds'), $item->getExpiration(), '', 1);
        $item->expiresAfter(new \DateInterval('P50D'));
        $this->assertEquals(strtotime('+50 days'), $item->getExpiration(), '', 1);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testItemGetExpiration(KeyValueStore $cache, Pool $pool)
    {
        // pointless, we've just tested this as part of the setExpiration series
    }
}
