<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

class ItemTest extends Psr6TestCase
{
    public function testItemGetKey()
    {
        $item = $this->pool->getItem('key');
        $this->assertEquals('key', $item->getKey());
    }

    public function testItemGetExisting()
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());
    }

    public function testItemGetExistingSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());
    }

    public function testItemGetNonExisting()
    {
        $item = $this->pool->getItem('key');
        $this->assertEquals(null, $item->get());
    }

    public function testItemSetExisting()
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    public function testItemSetExistingSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    public function testItemSetNonExisting()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    public function testItemIsHitExisting()
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');
        $this->assertEquals(true, $item->isHit());
    }

    public function testItemIsHitExistingSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');
        $this->assertEquals(true, $item->isHit());
    }

    public function testItemIsHitNonExisting()
    {
        $item = $this->pool->getItem('key');
        $this->assertEquals(false, $item->isHit());
    }

    public function testItemExpiresAtExisting()
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEquals(strtotime('tomorrow'), $item->getExpiration(), '', 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    public function testItemExpiresAtExistingSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEquals(strtotime('tomorrow'), $item->getExpiration(), '', 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    public function testItemExpiresAtNonExisting()
    {
        $item = $this->pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEquals(strtotime('tomorrow'), $item->getExpiration(), '', 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    public function testItemExpiresAfterExisting()
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');

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

    public function testItemExpiresAfterExistingSetViaPool()
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');

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

    public function testItemExpiresAfterNonExisting()
    {
        $item = $this->pool->getItem('key');

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

    public function testItemGetExpiration()
    {
        // pointless, we've just tested this as part of the setExpiration series
    }
}
