<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Adapters\MemoryStore;
use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\Exception\UnbegunTransaction;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;

abstract class AbstractTransactionalStoreTestCase extends AbstractKeyValueStoreTestCase
{
    public function getTestKeyValueStore(KeyValueStore $keyValueStore): KeyValueStore
    {
        return new TransactionalStore($keyValueStore);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->testKeyValueStore->begin();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        try {
            $this->testKeyValueStore->rollback();
        } catch (UnbegunTransaction $e) {
            // this is alright, guess we've terminated the transaction already
        }
    }

    public function testTransactionalGetAndSet(): void
    {
        $this->testKeyValueStore->set('key', 'value');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value is also set on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalGetFail(): void
    {
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalGetMulti(): void
    {
        $localValues = ['key' => 'value'];
        $cacheValues = ['key2' => 'value2'];

        foreach ($localValues as $key => $value) {
            $this->testKeyValueStore->set($key, $value);
        }

        foreach ($cacheValues as $key => $value) {
            $this->adapterKeyValueStore->set($key, $value);
        }

        // check that we're able to read the values from both buffered & real cache
        $this->assertEquals($localValues + $cacheValues, $this->testKeyValueStore->getMulti(array_keys($localValues + $cacheValues)));

        // tearDown will cleanup everything that's been stored via buffered cache,
        // however, this one went directly to real cache - clean up!
        $this->adapterKeyValueStore->delete('key2');
    }

    public function testTransactionalSetMulti(): void
    {
        $this->testKeyValueStore->setMulti([
            'key' => 'value',
            'key2' => 'value2',
        ]);

        // check that the values are only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the values are also set on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key2'));
    }

    public function testTransactionalDelete(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $this->testKeyValueStore->delete('key');

        // check that the value has been deleted from transactionalCache (only)
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been deleted from real cache
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalDeleteMulti(): void
    {
        $this->adapterKeyValueStore->setMulti([
            'key' => 'value',
            'key2' => 'value2',
        ]);

        $this->testKeyValueStore->deleteMulti(['key', 'key2']);

        // check that the values have been deleted from transactionalCache (only)
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key2'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the values have also been deleted from real cache
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }

    public function testTransactionalAdd(): void
    {
        $this->testKeyValueStore->add('key', 'value');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value is also added to real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalAddFailImmediately(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');
        $success = $this->testKeyValueStore->add('key', 'value2');
        $this->assertFalse($success);

        // commit shouldn't fail, there is nothing to commit (add just didn't go through)
        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value is not added on transactionalCache, nor on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalAddFailDeferred(): void
    {
        $this->testKeyValueStore->add('key', 'value');

        // something else directly sets the key in the meantime...
        $this->adapterKeyValueStore->set('key', 'value2');

        // check that the value has been added to buffered cache but not yet to real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertFalse($success);

        // check that the commit failed & the values did not persist
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalReplace(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');
        $this->testKeyValueStore->replace('key', 'value2');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value is also replaced in real cache
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalReplaceFailImmediately(): void
    {
        $success = $this->testKeyValueStore->replace('key', 'value');
        $this->assertFalse($success);

        // commit shouldn't fail, there is nothing to commit (replace just didn't go through)
        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value is not replaced on transactionalCache, nor on real cache
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalReplaceFailDeferred(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');
        $this->testKeyValueStore->replace('key', 'value2');

        // something else directly deletes the key in the meantime...
        $this->adapterKeyValueStore->delete('key');

        // check that the value has been replaced in buffered cache but not yet in real cache
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertFalse($success);

        // check that the commit failed & the values did not persist
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalCas(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $casToken = null;
        $this->testKeyValueStore->get('key', $casToken);
        $this->testKeyValueStore->cas($casToken, 'key', 'updated-value');

        // check that the value has been CAS'ed to transactionalCache (only)
        $this->assertEquals('updated-value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been CAS'ed to real cache
        $this->assertEquals('updated-value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('updated-value', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalConsecutiveCas(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $casToken = null;
        $this->testKeyValueStore->get('key', $casToken);
        $this->testKeyValueStore->cas($casToken, 'key', 'updated-value');
        $this->testKeyValueStore->get('key', $casToken);
        $this->testKeyValueStore->cas($casToken, 'key', 'updated-value2');

        // check that the value has been CAS'ed to transactionalCache (only)
        $this->assertEquals('updated-value2', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been CAS'ed to real cache
        $this->assertEquals('updated-value2', $this->testKeyValueStore->get('key'));
        $this->assertEquals('updated-value2', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalCasFailImmediately(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $casToken = null;
        $this->testKeyValueStore->get('key', $casToken);
        $success = $this->testKeyValueStore->cas('wrong-token', 'key', 'updated-value');
        $this->assertFalse($success);

        // commit shouldn't fail, there is nothing to commit (CAS just didn't go through)
        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value hasn't been CAS'ed anywhere
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalCasFailDeferred(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $casToken = null;
        $this->testKeyValueStore->get('key', $casToken);
        $this->testKeyValueStore->cas($casToken, 'key', 'updated-value');

        // something else directly overwrites key in the meantime...
        $this->adapterKeyValueStore->set('key', 'conflicting-value');

        // check that the value has been CAS'ed to buffered cache but not yet to real cache
        $this->assertEquals('updated-value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('conflicting-value', $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertFalse($success);

        // check that the commit failed & the values did not persist
        $this->assertEquals('conflicting-value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('conflicting-value', $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalIncrement(): void
    {
        $this->adapterKeyValueStore->set('key', 1);
        $this->testKeyValueStore->increment('key', 1, 1);

        // check that the value has been incremented on transactionalCache (only)
        $this->assertEquals(2, $this->testKeyValueStore->get('key'));
        $this->assertEquals(1, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been incremented on real cache
        $this->assertEquals(2, $this->testKeyValueStore->get('key'));
        $this->assertEquals(2, $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalIncrementInitialize(): void
    {
        $this->testKeyValueStore->increment('key', 1, 1);

        // check that the value has been set on transactionalCache (only)
        $this->assertEquals(1, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been incremented on real cache
        $this->assertEquals(1, $this->testKeyValueStore->get('key'));
        $this->assertEquals(1, $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalDecrement(): void
    {
        $this->adapterKeyValueStore->set('key', 1);
        $this->testKeyValueStore->decrement('key', 1, 1);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(1, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->adapterKeyValueStore->get('key'));

        // decrement again (can't go below 0)
        $this->testKeyValueStore->begin();
        $this->testKeyValueStore->decrement('key', 1, 1);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalDecrementInitialize(): void
    {
        $this->testKeyValueStore->decrement('key', 1, 0);

        // check that the value has been set on transactionalCache (only)
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->adapterKeyValueStore->get('key'));

        // decrement again (can't go below 0)
        $this->testKeyValueStore->begin();
        $this->testKeyValueStore->decrement('key', 1, 0);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->testKeyValueStore->get('key'));
        $this->assertEquals(0, $this->adapterKeyValueStore->get('key'));
    }

    public function testTransactionalTouch(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');
        $this->adapterKeyValueStore->set('key2', 'value2');

        $this->testKeyValueStore->touch('key', time() + 2);
        $this->testKeyValueStore->touch('key2', time() - 2);

        // expiration times are set on local, but not yet on real cache
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key2'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // expiration times have persisted on real cache too
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }

    public function testTransactionalFlush(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');
        $this->testKeyValueStore->set('key2', 'value2');

        $this->testKeyValueStore->flush();

        // check that real cache still isn't flushed, but memory is
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // everything should be gone by now!
        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }

    public function testTransactionalRollback(): void
    {
        $this->adapterKeyValueStore->set('key', 'value');

        $this->testKeyValueStore->set('key', 'value2');
        $this->testKeyValueStore->add('key2', 'value2');

        // something else directly sets the key in the meantime...
        $this->adapterKeyValueStore->set('key2', 'value');

        $success = $this->testKeyValueStore->commit();
        $this->assertFalse($success);

        // both changes should have been "rolled back" and both keys should've
        // remained unaltered
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key2'));
    }

    public function testTransactionalNestedTransactionCommit(): void
    {
        // transaction has already been started in adapterProvider,
        // let's write to it
        $this->testKeyValueStore->set('key', 'value');

        // verify that the value has not yet been committed to real cache, but
        // can be read from the transactional layer
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        // start a nested transaction & store another value
        $this->testKeyValueStore->begin();
        $this->testKeyValueStore->set('key2', 'value');

        // verify that none of the values have not yet been committed to real
        // cache, but both can be read from the transactional layer
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));

        // commit nested transaction
        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // verify that none of the values have not yet been committed to real
        // cache, but both can be read from the transactional layer
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));

        // commit parent transaction
        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // verify that all values have persisted to real & can still be read
        // from the transactional layer
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertEquals('value', $this->testKeyValueStore->get('key2'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key2'));
    }

    public function testTransactionalNestedTransactionRollback(): void
    {
        // transaction has already been started in adapterProvider,
        // let's write to it
        $this->testKeyValueStore->set('key', 'value');

        // start a nested transaction & store another value
        $this->testKeyValueStore->begin();
        $this->testKeyValueStore->set('key2', 'value');

        // we don't need to test values up to this point
        // this has already been tested in testNestedTransactionCommit

        // roll back nested transaction
        $success = $this->testKeyValueStore->rollback();
        $this->assertTrue($success);

        // verify that the value stored in parent transaction still exists
        // (but hasn't yet been committed to real cache), but that the value
        // in the rolled back transaction is gone)
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));

        // commit parent transaction
        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);

        // verify that the value stored in parent transaction has persisted
        // to real cache, but that the value in the rolled back transaction
        // is gone
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $this->adapterKeyValueStore->get('key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
        $this->assertFalse($this->adapterKeyValueStore->get('key2'));
    }

    /**
     * Confirm that multiple set() calls are combined into 1 setMulti().
     */
    public function testTransactionalOptimizedSet(): void
    {
        $mock = $this->getMockBuilder(MemoryStore::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->once())
            ->method('setMulti')
            ->willReturnCallback(static function ($items) {
                return array_fill_keys(array_keys($items), true);
            });
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
    public function testTransactionalOptimizedSetMultipleExpiration(): void
    {
        $mock = $this->getMockBuilder(MemoryStore::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->exactly(2))
            ->method('setMulti')
            ->willReturnCallback(static function ($items) {
                return array_fill_keys(array_keys($items), true);
            });
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
    public function testTransactionalCasCombination(): void
    {
        $this->testKeyValueStore->set('key', 'value');
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->get('key', $token);
        $success = $this->testKeyValueStore->cas($token, 'key', 'value2');
        $this->assertTrue($success);
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->set('key', 'value3');
        $this->assertEquals('value3', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals('value3', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value3', $this->adapterKeyValueStore->get('key'));
    }

    /**
     * @see testCasCombination
     */
    public function testTransactionalCasCombination2(): void
    {
        $this->testKeyValueStore->set('key', 'value');
        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->get('key', $token);
        $success = $this->testKeyValueStore->cas($token, 'key', 'value2');
        $this->assertTrue($success);
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals('value2', $this->testKeyValueStore->get('key'));
        $this->assertEquals('value2', $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Increments are combined through a complex process. This verifies
     * that the offset is correctly adjusted throughout this process.
     */
    public function testTransactionalMultipleIncrementsOffset(): void
    {
        $this->adapterKeyValueStore->set('key', 3);

        $this->testKeyValueStore->increment('key', 2, 10);
        $this->assertEquals(5, $this->testKeyValueStore->get('key'));
        $this->assertEquals(3, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 1, 30);
        $this->assertEquals(6, $this->testKeyValueStore->get('key'));
        $this->assertEquals(3, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 3, 50);
        $this->assertEquals(9, $this->testKeyValueStore->get('key'));
        $this->assertEquals(3, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(9, $this->testKeyValueStore->get('key'));
        $this->assertEquals(9, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testTransactionalMultipleIncrementsInitial(): void
    {
        $this->testKeyValueStore->increment('key', 2, 10);
        $this->assertEquals(10, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 1, 30);
        $this->assertEquals(11, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 3, 50);
        $this->assertEquals(14, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(14, $this->testKeyValueStore->get('key'));
        $this->assertEquals(14, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Decrements are combined through a complex process. This verifies
     * that the offset is correctly adjusted throughout this process.
     */
    public function testTransactionalMultipleDecrementsOffset(): void
    {
        $this->adapterKeyValueStore->set('key', 20);

        $this->testKeyValueStore->decrement('key', 2, 10);
        $this->assertEquals(18, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 1, 30);
        $this->assertEquals(17, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 3, 50);
        $this->assertEquals(14, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(14, $this->testKeyValueStore->get('key'));
        $this->assertEquals(14, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testTransactionalMultipleDecrementsInitial(): void
    {
        $this->testKeyValueStore->decrement('key', 2, 10);
        $this->assertEquals(10, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 1, 30);
        $this->assertEquals(9, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 3, 50);
        $this->assertEquals(6, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(6, $this->testKeyValueStore->get('key'));
        $this->assertEquals(6, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Increments & decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testTransactionalMixedIncrementDecrementsOffset(): void
    {
        $this->adapterKeyValueStore->set('key', 20);

        $this->testKeyValueStore->increment('key', 2, 10);
        $this->assertEquals(22, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 1, 30);
        $this->assertEquals(21, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 3, 50);
        $this->assertEquals(24, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(24, $this->testKeyValueStore->get('key'));
        $this->assertEquals(24, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Increments & decrements are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testTransactionalMixedIncrementDecrementsInitial(): void
    {
        $this->testKeyValueStore->increment('key', 2, 10);
        $this->assertEquals(10, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 1, 30);
        $this->assertEquals(9, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 3, 50);
        $this->assertEquals(12, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(12, $this->testKeyValueStore->get('key'));
        $this->assertEquals(12, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Decrements & increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testTransactionalMixedDecrementIncrementsOffset(): void
    {
        $this->adapterKeyValueStore->set('key', 20);

        $this->testKeyValueStore->decrement('key', 2, 10);
        $this->assertEquals(18, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 1, 30);
        $this->assertEquals(19, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 3, 50);
        $this->assertEquals(16, $this->testKeyValueStore->get('key'));
        $this->assertEquals(20, $this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(16, $this->testKeyValueStore->get('key'));
        $this->assertEquals(16, $this->adapterKeyValueStore->get('key'));
    }

    /**
     * Decrements & increments are combined through a complex process. This verifies
     * that the initial value is correctly adjusted throughout this process.
     */
    public function testTransactionalMixedDecrementIncrementsInitial(): void
    {
        $this->testKeyValueStore->decrement('key', 2, 10);
        $this->assertEquals(10, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->increment('key', 1, 30);
        $this->assertEquals(11, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $this->testKeyValueStore->decrement('key', 3, 50);
        $this->assertEquals(8, $this->testKeyValueStore->get('key'));
        $this->assertFalse($this->adapterKeyValueStore->get('key'));

        $success = $this->testKeyValueStore->commit();
        $this->assertTrue($success);
        $this->assertEquals(8, $this->testKeyValueStore->get('key'));
        $this->assertEquals(8, $this->adapterKeyValueStore->get('key'));
    }
}
