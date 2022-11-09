<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

class ItemTest extends Psr6TestCase
{
    public function testItemGetKey(): void
    {
        $item = $this->pool->getItem('key');
        $this->assertEquals('key', $item->getKey());
    }

    public function testItemGetExisting(): void
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());
    }

    public function testItemGetExistingSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');
        $this->assertEquals('value', $item->get());
    }

    public function testItemGetNonExisting(): void
    {
        $item = $this->pool->getItem('key');
        $this->assertNull($item->get());
    }

    public function testItemSetExisting(): void
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    public function testItemSetExistingSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    public function testItemSetNonExisting(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->assertEquals('value', $item->get());
    }

    public function testItemIsHitExisting(): void
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');
        $this->assertTrue($item->isHit());
    }

    public function testItemIsHitExistingSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');
        $this->assertTrue($item->isHit());
    }

    public function testItemIsHitNonExisting(): void
    {
        $item = $this->pool->getItem('key');
        $this->assertFalse($item->isHit());
    }

    public function testItemExpiresAtExisting(): void
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEqualsWithDelta(strtotime('tomorrow'), $item->getExpiration(), 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    public function testItemExpiresAtExistingSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEqualsWithDelta(strtotime('tomorrow'), $item->getExpiration(), 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    public function testItemExpiresAtNonExisting(): void
    {
        $item = $this->pool->getItem('key');

        // DateTime object
        $item->expiresAt(new \DateTime('tomorrow'));
        $this->assertEqualsWithDelta(strtotime('tomorrow'), $item->getExpiration(), 1);

        // permanent
        $item->expiresAt(null);
        $this->assertEquals(0, $item->getExpiration());
    }

    public function testItemExpiresAfterExisting(): void
    {
        $this->cache->set('key', 'value');

        $item = $this->pool->getItem('key');

        // relative time, both small and large
        $item->expiresAfter(5);
        $this->assertEqualsWithDelta(strtotime('+5 seconds'), $item->getExpiration(), 1);
        $item->expiresAfter(50 * 24 * 60 * 60);
        $this->assertEqualsWithDelta(strtotime('+50 days'), $item->getExpiration(), 1);

        // DateInterval object
        $item->expiresAfter(new \DateInterval('PT5S'));
        $this->assertEqualsWithDelta(strtotime('+5 seconds'), $item->getExpiration(), 1);
        $item->expiresAfter(new \DateInterval('P50D'));
        $this->assertEqualsWithDelta(strtotime('+50 days'), $item->getExpiration(), 1);
    }

    public function testItemExpiresAfterExistingSetViaPool(): void
    {
        $item = $this->pool->getItem('key');
        $item->set('value');
        $this->pool->saveDeferred($item);
        $this->pool->commit();

        $item = $this->pool->getItem('key');

        // relative time, both small and large
        $item->expiresAfter(5);
        $this->assertEqualsWithDelta(strtotime('+5 seconds'), $item->getExpiration(), 1);
        $item->expiresAfter(50 * 24 * 60 * 60);
        $this->assertEqualsWithDelta(strtotime('+50 days'), $item->getExpiration(), 1);

        // DateInterval object
        $item->expiresAfter(new \DateInterval('PT5S'));
        $this->assertEqualsWithDelta(strtotime('+5 seconds'), $item->getExpiration(), 1);
        $item->expiresAfter(new \DateInterval('P50D'));
        $this->assertEqualsWithDelta(strtotime('+50 days'), $item->getExpiration(), 1);
    }

    public function testItemExpiresAfterNonExisting(): void
    {
        $item = $this->pool->getItem('key');

        // relative time, both small and large
        $item->expiresAfter(5);
        $this->assertEqualsWithDelta(strtotime('+5 seconds'), $item->getExpiration(), 1);
        $item->expiresAfter(50 * 24 * 60 * 60);
        $this->assertEqualsWithDelta(strtotime('+50 days'), $item->getExpiration(), 1);

        // DateInterval object
        $item->expiresAfter(new \DateInterval('PT5S'));
        $this->assertEqualsWithDelta(strtotime('+5 seconds'), $item->getExpiration(), 1);
        $item->expiresAfter(new \DateInterval('P50D'));
        $this->assertEqualsWithDelta(strtotime('+50 days'), $item->getExpiration(), 1);
    }
}
