<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterTestCase;

class TransactionalStoreTest extends AdapterTestCase
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

    public function testGetAndSet()
    {
        $this->transactionalCache->set('key', 'value');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value is also set on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testGetFail()
    {
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testGetMulti()
    {
        $localValues = array(
            'key' => 'value',
        );
        $cacheValues = array(
            'key2' => 'value2',
        );

        foreach ($localValues as $key => $value) {
            $this->transactionalCache->set($key, $value);
        }

        foreach ($cacheValues as $key => $value) {
            $this->cache->set($key, $value);
        }

        // check that we're able to read the values from both buffered & real cache
        $this->assertEquals($localValues + $cacheValues, $this->transactionalCache->getMulti(array_keys($localValues + $cacheValues)));

        // tearDown will cleanup everything that's been stored via buffered cache,
        // however, this one went directly to real cache - clean up!
        $this->cache->delete('key2');
    }

    public function testSetMulti()
    {
        $this->transactionalCache->setMulti(array(
            'key' => 'value',
            'key2' => 'value2',
        ));

        // check that the values are only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value2', $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->cache->get('key2'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the values are also set on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value2', $this->transactionalCache->get('key2'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testDelete()
    {
        $this->cache->set('key', 'value');

        $this->transactionalCache->delete('key');

        // check that the value has been deleted from transactionalCache (only)
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been deleted from real cache
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testDeleteMulti()
    {
        $this->cache->setMulti(array(
            'key' => 'value',
            'key2' => 'value2',
        ));

        $this->transactionalCache->deleteMulti(array('key', 'key2'));

        // check that the values have been deleted from transactionalCache (only)
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals('value2', $this->cache->get('key2'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the values have also been deleted from real cache
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testAdd()
    {
        $this->transactionalCache->add('key', 'value');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value is also added to real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testAddFailImmediately()
    {
        $this->cache->set('key', 'value');
        $success = $this->transactionalCache->add('key', 'value2');
        $this->assertFalse($success);

        // commit shouldn't fail, there is nothing to commit (add just didn't go through)
        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value is not added on transactionalCache, nor on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testAddFailDeferred()
    {
        $this->transactionalCache->add('key', 'value');

        // something else directly sets the key in the meantime...
        $this->cache->set('key', 'value2');

        // check that the value has been added to buffered cache but not yet to real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value2', $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertFalse($success);

        // check that the commit failed & the values did not persist
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals('value2', $this->cache->get('key'));
    }

    public function testReplace()
    {
        $this->cache->set('key', 'value');
        $this->transactionalCache->replace('key', 'value2');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value is also replaced in real cache
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals('value2', $this->cache->get('key'));
    }

    public function testReplaceFailImmediately()
    {
        $success = $this->transactionalCache->replace('key', 'value');
        $this->assertFalse($success);

        // commit shouldn't fail, there is nothing to commit (replace just didn't go through)
        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value is not replaced on transactionalCache, nor on real cache
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testReplaceFailDeferred()
    {
        $this->cache->set('key', 'value');
        $this->transactionalCache->replace('key', 'value2');

        // something else directly deletes the key in the meantime...
        $this->cache->delete('key');

        // check that the value has been replaced in buffered cache but not yet in real cache
        $this->assertEquals('value2', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertFalse($success);

        // check that the commit failed & the values did not persist
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testCas()
    {
        $this->cache->set('key', 'value');

        $casToken = null;
        $this->transactionalCache->get('key', $casToken);
        $this->transactionalCache->cas($casToken, 'key', 'updated-value');

        // check that the value has been CAS'ed to transactionalCache (only)
        $this->assertEquals('updated-value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been CAS'ed to real cache
        $this->assertEquals('updated-value', $this->transactionalCache->get('key'));
        $this->assertEquals('updated-value', $this->cache->get('key'));
    }

    public function testConsecutiveCas()
    {
        $this->cache->set('key', 'value');

        $casToken = null;
        $this->transactionalCache->get('key', $casToken);
        $this->transactionalCache->cas($casToken, 'key', 'updated-value');
        $this->transactionalCache->get('key', $casToken);
        $this->transactionalCache->cas($casToken, 'key', 'updated-value2');

        // check that the value has been CAS'ed to transactionalCache (only)
        $this->assertEquals('updated-value2', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been CAS'ed to real cache
        $this->assertEquals('updated-value2', $this->transactionalCache->get('key'));
        $this->assertEquals('updated-value2', $this->cache->get('key'));
    }

    public function testCasFailImmediately()
    {
        $this->cache->set('key', 'value');

        $casToken = null;
        $this->transactionalCache->get('key', $casToken);
        $success = $this->transactionalCache->cas('wrong-token', 'key', 'updated-value');
        $this->assertFalse($success);

        // commit shouldn't fail, there is nothing to commit (CAS just didn't go through)
        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value hasn't been CAS'ed anywhere
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testCasFailDeferred()
    {
        $this->cache->set('key', 'value');

        $casToken = null;
        $this->transactionalCache->get('key', $casToken);
        $this->transactionalCache->cas($casToken, 'key', 'updated-value');

        // something else directly overwrites key in the meantime...
        $this->cache->set('key', 'conflicting-value');

        // check that the value has been CAS'ed to buffered cache but not yet to real cache
        $this->assertEquals('updated-value', $this->transactionalCache->get('key'));
        $this->assertEquals('conflicting-value', $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertFalse($success);

        // check that the commit failed & the values did not persist
        $this->assertEquals('conflicting-value', $this->transactionalCache->get('key'));
        $this->assertEquals('conflicting-value', $this->cache->get('key'));
    }

    public function testIncrement()
    {
        $this->cache->set('key', 1);
        $this->transactionalCache->increment('key', 1, 1);

        // check that the value has been incremented on transactionalCache (only)
        $this->assertEquals(2, $this->transactionalCache->get('key'));
        $this->assertEquals(1, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been incremented on real cache
        $this->assertEquals(2, $this->transactionalCache->get('key'));
        $this->assertEquals(2, $this->cache->get('key'));
    }

    public function testIncrementInitialize()
    {
        $this->transactionalCache->increment('key', 1, 1);

        // check that the value has been set on transactionalCache (only)
        $this->assertEquals(1, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been incremented on real cache
        $this->assertEquals(1, $this->transactionalCache->get('key'));
        $this->assertEquals(1, $this->cache->get('key'));
    }

    public function testDecrement()
    {
        $this->cache->set('key', 1);
        $this->transactionalCache->decrement('key', 1, 1);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(1, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(0, $this->cache->get('key'));

        // decrement again (can't go below 0)
        $this->transactionalCache->begin();
        $this->transactionalCache->decrement('key', 1, 1);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(0, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(0, $this->cache->get('key'));
    }

    public function testDecrementInitialize()
    {
        $this->transactionalCache->decrement('key', 1, 0);

        // check that the value has been set on transactionalCache (only)
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(0, $this->cache->get('key'));

        // decrement again (can't go below 0)
        $this->transactionalCache->begin();
        $this->transactionalCache->decrement('key', 1, 0);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(0, $this->cache->get('key'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $this->transactionalCache->get('key'));
        $this->assertEquals(0, $this->cache->get('key'));
    }

    public function testTouch()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value2');

        $this->transactionalCache->touch('key', time() + 2);
        $this->transactionalCache->touch('key2', time() - 2);

        // expiration times are set on local, but not yet on real cache
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals('value2', $this->cache->get('key2'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // expiration times have persisted on real cache too
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testFlush()
    {
        $this->cache->set('key', 'value');
        $this->transactionalCache->set('key2', 'value2');

        $this->transactionalCache->flush();

        // check that real cache still isn't flushed, but memory is
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));

        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // everything should be gone by now!
        $this->assertEquals(false, $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testRollback()
    {
        $this->cache->set('key', 'value');

        $this->transactionalCache->set('key', 'value2');
        $this->transactionalCache->add('key2', 'value2');

        // something else directly sets the key in the meantime...
        $this->cache->set('key2', 'value');

        $success = $this->transactionalCache->commit();
        $this->assertFalse($success);

        // both changes should have been "rolled back" and both keys should've
        // remained unaltered
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->transactionalCache->get('key2'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('value', $this->cache->get('key2'));
    }

    public function testNestedTransactionCommit()
    {
        // transaction has already been started in adapterProvider,
        // let's write to it
        $this->transactionalCache->set('key', 'value');

        // verify that the value has not yet been committed to real cache, but
        // can be read from the transactional layer
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));

        // start a nested transaction & store another value
        $this->transactionalCache->begin();
        $this->transactionalCache->set('key2', 'value');

        // verify that none of the values have not yet been committed to real
        // cache, but both can be read from the transactional layer
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals('value', $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));

        // commit nested transaction
        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // verify that none of the values have not yet been committed to real
        // cache, but both can be read from the transactional layer
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals('value', $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));

        // commit parent transaction
        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // verify that all values have persisted to real & can still be read
        // from the transactional layer
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('value', $this->transactionalCache->get('key2'));
        $this->assertEquals('value', $this->cache->get('key2'));
    }

    public function testNestedTransactionRollback()
    {
        // transaction has already been started in adapterProvider,
        // let's write to it
        $this->transactionalCache->set('key', 'value');

        // start a nested transaction & store another value
        $this->transactionalCache->begin();
        $this->transactionalCache->set('key2', 'value');

        // we don't need to test values up to this point
        // this has already been tested in testNestedTransactionCommit

        // roll back nested transaction
        $success = $this->transactionalCache->rollback();
        $this->assertTrue($success);

        // verify that the value stored in parent transaction still exists
        // (but hasn't yet been committed to real cache), but that the value
        // in the rolled back transaction is gone)
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));

        // commit parent transaction
        $success = $this->transactionalCache->commit();
        $this->assertTrue($success);

        // verify that the value stored in parent transaction has persisted
        // to real cache, but that the value in the rolled back transaction
        // is gone
        $this->assertEquals('value', $this->transactionalCache->get('key'));
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals(false, $this->transactionalCache->get('key2'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }
}
