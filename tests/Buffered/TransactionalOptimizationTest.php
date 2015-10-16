<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;

class TransactionalOptimizationTest extends AdapterProviderTestCase
{
    public function adapterProvider()
    {
        parent::adapterProvider();

        return array_map(function (KeyValueStore $adapter) {
            $transactionalCache = new TransactionalStore($adapter);
            $transactionalCache->begin();

            return array($transactionalCache, $adapter);
        }, $this->adapters);
    }

    /**
     * Confirm that multiple set() calls are combined into 1 setMulti().
     */
    public function testOptimizedSet()
    {
        $mock = $this->getMockBuilder('MatthiasMullie\\Scrapbook\\Adapters\\MemoryStore')
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->once())
            ->method('setMulti')
            ->will($this->returnCallback(function ($items, $expire) {
                return array_fill_keys(array_keys($items), true);
            }));
        $mock->expects($this->exactly(0))
            ->method('set')
            ->willReturn(true);

        $transactionalCache = new TransactionalStore($mock);
        $transactionalCache->begin();
        $transactionalCache->set('key', 'value');
        $transactionalCache->set('key2', 'value');
        $transactionalCache->commit();
    }

    /**
     * Confirm that multiple set() calls with different expiration times
     * are combined into multiple setMulti() calls.
     */
    public function testOptimizedSetMultipleExpiration()
    {
        $mock = $this->getMockBuilder('MatthiasMullie\\Scrapbook\\Adapters\\MemoryStore')
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->exactly(2))
            ->method('setMulti')
            ->will($this->returnCallback(function ($items, $expire) {
                return array_fill_keys(array_keys($items), true);
            }));
        $mock->expects($this->exactly(0))
            ->method('set')
            ->willReturn(true);

        $transactionalCache = new TransactionalStore($mock);
        $transactionalCache->begin();
        $transactionalCache->set('key', 'value', 5);
        $transactionalCache->set('key2', 'value', 5);
        $transactionalCache->set('key3', 'value', 10);
        $transactionalCache->commit();
    }

    /**
     * CAS is special: it has preconditions (token/value must match
     * a specific value) and that could potentially clash with
     * the order of operations being changed (e.g. CAS is executed
     * before SET).
     *
     * @dataProvider adapterProvider
     */
    public function testCasCombination(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->set('key', 'value');
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->get('key', $token);
        $success = $transactionalCache->cas($token, 'key', 'value2');
        $this->assertTrue($success);
        $this->assertEquals('value2', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->set('key', 'value3');
        $this->assertEquals('value3', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals('value3', $transactionalCache->get('key'));
        $this->assertEquals('value3', $cache->get('key'));
    }

    /**
     * @see testCasCombination
     * @dataProvider adapterProvider
     */
    public function testCasCombination2(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->set('key', 'value');
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->get('key', $token);
        $success = $transactionalCache->cas($token, 'key', 'value2');
        $this->assertTrue($success);
        $this->assertEquals('value2', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals('value2', $transactionalCache->get('key'));
        $this->assertEquals('value2', $cache->get('key'));
    }

    /**
     * Increments are combined through a complex process. This verifies
     * that the offset is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMultipleIncrementsOffset(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 3);

        $transactionalCache->increment('key', 2, 10);
        $this->assertEquals(5, $transactionalCache->get('key'));
        $this->assertEquals(3, $cache->get('key'));

        $transactionalCache->increment('key', 1, 30);
        $this->assertEquals(6, $transactionalCache->get('key'));
        $this->assertEquals(3, $cache->get('key'));

        $transactionalCache->increment('key', 3, 50);
        $this->assertEquals(9, $transactionalCache->get('key'));
        $this->assertEquals(3, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(9, $transactionalCache->get('key'));
        $this->assertEquals(9, $cache->get('key'));
    }

    /**
     * Increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMultipleIncrementsInitial(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->increment('key', 2, 10);
        $this->assertEquals(10, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->increment('key', 1, 30);
        $this->assertEquals(11, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->increment('key', 3, 50);
        $this->assertEquals(14, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(14, $transactionalCache->get('key'));
        $this->assertEquals(14, $cache->get('key'));
    }

    /**
     * Decrements are combined through a complex process. This verifies
     * that the offset is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMultipleDecrementsOffset(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 20);

        $transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(18, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(17, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(14, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(14, $transactionalCache->get('key'));
        $this->assertEquals(14, $cache->get('key'));
    }

    /**
     * Decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMultipleDecrementsInitial(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(10, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(9, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(6, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(6, $transactionalCache->get('key'));
        $this->assertEquals(6, $cache->get('key'));
    }

    /**
     * Increments & decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMixedIncrementDecrementsOffset(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 20);

        $transactionalCache->increment('key', 2, 10);
        $this->assertEquals(22, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(21, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $transactionalCache->increment('key', 3, 50);
        $this->assertEquals(24, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(24, $transactionalCache->get('key'));
        $this->assertEquals(24, $cache->get('key'));
    }

    /**
     * Increments & decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMixedIncrementDecrementsInitial(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->increment('key', 2, 10);
        $this->assertEquals(10, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(9, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->increment('key', 3, 50);
        $this->assertEquals(12, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(12, $transactionalCache->get('key'));
        $this->assertEquals(12, $cache->get('key'));
    }

    /**
     * Decrements & increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMixedDecrementIncrementsOffset(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 20);

        $transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(18, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $transactionalCache->increment('key', 1, 30);
        $this->assertEquals(19, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(16, $transactionalCache->get('key'));
        $this->assertEquals(20, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(16, $transactionalCache->get('key'));
        $this->assertEquals(16, $cache->get('key'));
    }

    /**
     * Decrements & increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     *
     * @dataProvider adapterProvider
     */
    public function testMixedDecrementIncrementsInitial(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(10, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->increment('key', 1, 30);
        $this->assertEquals(11, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(8, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $success = $transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(8, $transactionalCache->get('key'));
        $this->assertEquals(8, $cache->get('key'));
    }
}
