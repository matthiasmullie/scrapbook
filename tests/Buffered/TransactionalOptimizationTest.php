<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTest;

class TransactionalOptimizationTest extends AdapterTest
{
    /**
     * @var TransactionalStore
     */
    protected $transactionalCache;

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
        $this->transactionalCache = new TransactionalStore($adapter);
    }

    public function setUp()
    {
        parent::setUp();

        $this->transactionalCache->begin();
    }

    public function tearDown()
    {
        parent::tearDown();

        $this->transactionalCache->rollback();
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
     */
    public function testCasCombination()
    {
        $this->transactionalCache->set('key', 'value');
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->get('key', $token);
        $success = $this->transactionalCache->cas($token, 'key', 'value2');
        $this->assertTrue($success);
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->set('key', 'value3');
        $this->assertEquals('value3', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals('value3', $this->transactionalCache->get('key'));
        $this->assertEquals('value3', $this->cache->get('key'));
    }

    /**
     * @see testCasCombination
     */
    public function testCasCombination2()
    {
        $this->transactionalCache->set('key', 'value');
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->get('key', $token);
        $success = $this->transactionalCache->cas($token, 'key', 'value2');
        $this->assertTrue($success);
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals('value2', $this->cache->get('key'));
    }

    /**
     * Increments are combined through a complex process. This verifies
     * that the offset is correctly adjusted throughout this process.
     */
    public function testMultipleIncrementsOffset()
    {
        $this->cache->set('key', 3);

        $this->transactionalCache->increment('key', 2, 10);
        $this->assertEquals(5, $this->transactionalCache->get('key'));
        $this->assertEquals(3, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 1, 30);
        $this->assertEquals(6, $this->transactionalCache->get('key'));
        $this->assertEquals(3, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 3, 50);
        $this->assertEquals(9, $this->transactionalCache->get('key'));
        $this->assertEquals(3, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(9, $this->transactionalCache->get('key'));
        $this->assertEquals(9, $this->cache->get('key'));
    }

    /**
     * Increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testMultipleIncrementsInitial()
    {
        $this->transactionalCache->increment('key', 2, 10);
        $this->assertEquals(10, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 1, 30);
        $this->assertEquals(11, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 3, 50);
        $this->assertEquals(14, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(14, $this->transactionalCache->get('key'));
        $this->assertEquals(14, $this->cache->get('key'));
    }

    /**
     * Decrements are combined through a complex process. This verifies
     * that the offset is correctly adjusted throughout this process.
     */
    public function testMultipleDecrementsOffset()
    {
        $this->cache->set('key', 20);

        $this->transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(18, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(17, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(14, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(14, $this->transactionalCache->get('key'));
        $this->assertEquals(14, $this->cache->get('key'));
    }

    /**
     * Decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testMultipleDecrementsInitial()
    {
        $this->transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(10, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(9, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(6, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(6, $this->transactionalCache->get('key'));
        $this->assertEquals(6, $this->cache->get('key'));
    }

    /**
     * Increments & decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testMixedIncrementDecrementsOffset()
    {
        $this->cache->set('key', 20);

        $this->transactionalCache->increment('key', 2, 10);
        $this->assertEquals(22, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(21, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 3, 50);
        $this->assertEquals(24, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(24, $this->transactionalCache->get('key'));
        $this->assertEquals(24, $this->cache->get('key'));
    }

    /**
     * Increments & decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testMixedIncrementDecrementsInitial()
    {
        $this->transactionalCache->increment('key', 2, 10);
        $this->assertEquals(10, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 1, 30);
        $this->assertEquals(9, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 3, 50);
        $this->assertEquals(12, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(12, $this->transactionalCache->get('key'));
        $this->assertEquals(12, $this->cache->get('key'));
    }

    /**
     * Decrements & increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testMixedDecrementIncrementsOffset()
    {
        $this->cache->set('key', 20);

        $this->transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(18, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 1, 30);
        $this->assertEquals(19, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(16, $this->transactionalCache->get('key'));
        $this->assertEquals(20, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(16, $this->transactionalCache->get('key'));
        $this->assertEquals(16, $this->cache->get('key'));
    }

    /**
     * Decrements & increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testMixedDecrementIncrementsInitial()
    {
        $this->transactionalCache->decrement('key', 2, 10);
        $this->assertEquals(10, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->increment('key', 1, 30);
        $this->assertEquals(11, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $this->transactionalCache->decrement('key', 3, 50);
        $this->assertEquals(8, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);
        $this->assertEquals(8, $this->transactionalCache->get('key'));
        $this->assertEquals(8, $this->cache->get('key'));
    }
}
