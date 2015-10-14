<?php

namespace MatthiasMullie\Scrapbook\Tests\Buffered;

use MatthiasMullie\Scrapbook\Buffered\TransactionalStore;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;

class TransactionalStoreTest extends AdapterProviderTestCase
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
     * @dataProvider adapterProvider
     */
    public function testGetAndSet(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->set('key', 'value');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value is also set on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetFail(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetMulti(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $localValues = array(
            'key' => 'value',
        );
        $cacheValues = array(
            'key2' => 'value2',
        );

        foreach ($localValues as $key => $value) {
            $transactionalCache->set($key, $value);
        }

        foreach ($cacheValues as $key => $value) {
            $cache->set($key, $value);
        }

        // check that we're able to read the values from both buffered & real cache
        $this->assertEquals($localValues + $cacheValues, $transactionalCache->getMulti(array_keys($localValues + $cacheValues)));

        // tearDown will cleanup everything that's been stored via buffered cache,
        // however, this one went directly to real cache - clean up!
        $cache->delete('key2');
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSetMulti(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->setMulti(array(
            'key' => 'value',
            'key2' => 'value2',
        ));

        // check that the values are only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value2', $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $cache->get('key2'));

        $transactionalCache->commit();

        // check that the values are also set on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value2', $transactionalCache->get('key2'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals('value2', $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDelete(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $transactionalCache->delete('key');

        // check that the value has been deleted from transactionalCache (only)
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been deleted from real cache
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDeleteMulti(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->setMulti(array(
            'key' => 'value',
            'key2' => 'value2',
        ));

        $transactionalCache->deleteMulti(array('key', 'key2'));

        // check that the values have been deleted from transactionalCache (only)
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals('value2', $cache->get('key2'));

        $transactionalCache->commit();

        // check that the values have also been deleted from real cache
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testAdd(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->add('key', 'value');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value is also added to real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testAddFailImmediately(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $transactionalCache->add('key', 'value2');

        $transactionalCache->commit();

        // check that the value is not added on transactionalCache, nor on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testAddFailDeferred(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->add('key', 'value');

        // something else directly sets the key in the meantime...
        $cache->set('key', 'value2');

        // check that the value has been added to buffered cache but not yet to real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value2', $cache->get('key'));

        $transactionalCache->commit();

        // check that the value failed to add and the key was properly cleared
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplace(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $transactionalCache->replace('key', 'value2');

        // check that the value is only set on transactionalCache, not yet on real cache
        $this->assertEquals('value2', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));

        $transactionalCache->commit();

        // check that the value is also replaced in real cache
        $this->assertEquals('value2', $transactionalCache->get('key'));
        $this->assertEquals('value2', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplaceFailImmediately(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->replace('key', 'value');

        $transactionalCache->commit();

        // check that the value is not replaced on transactionalCache, nor on real cache
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplaceFailDeferred(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $transactionalCache->replace('key', 'value2');

        // something else directly deletes the key in the meantime...
        $cache->delete('key');

        // check that the value has been replaced in buffered cache but not yet in real cache
        $this->assertEquals('value2', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value failed to add and the key was properly cleared
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCas(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $casToken = null;
        $transactionalCache->get('key', $casToken);
        $transactionalCache->cas($casToken, 'key', 'updated-value');

        // check that the value has been CAS'ed to transactionalCache (only)
        $this->assertEquals('updated-value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been CAS'ed to real cache
        $this->assertEquals('updated-value', $transactionalCache->get('key'));
        $this->assertEquals('updated-value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testConsecutiveCas(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $casToken = null;
        $transactionalCache->get('key', $casToken);
        $transactionalCache->cas($casToken, 'key', 'updated-value');
        $transactionalCache->get('key', $casToken);
        $transactionalCache->cas($casToken, 'key', 'updated-value2');

        // check that the value has been CAS'ed to transactionalCache (only)
        $this->assertEquals('updated-value2', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been CAS'ed to real cache
        $this->assertEquals('updated-value2', $transactionalCache->get('key'));
        $this->assertEquals('updated-value2', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasFailImmediately(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $casToken = null;
        $transactionalCache->get('key', $casToken);
        $transactionalCache->cas('wrong-token', 'key', 'updated-value');

        $transactionalCache->commit();

        // check that the value hasn't been CAS'ed anywhere
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasFailDeferred(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $casToken = null;
        $transactionalCache->get('key', $casToken);
        $transactionalCache->cas($casToken, 'key', 'updated-value');

        // something else directly overwrites key in the meantime...
        $cache->set('key', 'conflicting-value');

        // check that the value has been CAS'ed to buffered cache but not yet to real cache
        $this->assertEquals('updated-value', $transactionalCache->get('key'));
        $this->assertEquals('conflicting-value', $cache->get('key'));

        $transactionalCache->commit();

        // check that the value failed to CAS
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testIncrement(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 1);
        $transactionalCache->increment('key', 1, 1);

        // check that the value has been incremented on transactionalCache (only)
        $this->assertEquals(2, $transactionalCache->get('key'));
        $this->assertEquals(1, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been incremented on real cache
        $this->assertEquals(2, $transactionalCache->get('key'));
        $this->assertEquals(2, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testIncrementInitialize(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->increment('key', 1, 1);

        // check that the value has been set on transactionalCache (only)
        $this->assertEquals(1, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been incremented on real cache
        $this->assertEquals(1, $transactionalCache->get('key'));
        $this->assertEquals(1, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDecrement(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 1);
        $transactionalCache->decrement('key', 1, 1);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(1, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(0, $cache->get('key'));

        // decrement again (can't go below 0)
        $transactionalCache->begin();
        $transactionalCache->decrement('key', 1, 1);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(0, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(0, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDecrementInitialize(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $transactionalCache->decrement('key', 1, 0);

        // check that the value has been set on transactionalCache (only)
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(0, $cache->get('key'));

        // decrement again (can't go below 0)
        $transactionalCache->begin();
        $transactionalCache->decrement('key', 1, 0);

        // check that the value has been decremented on transactionalCache (only)
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(0, $cache->get('key'));

        $transactionalCache->commit();

        // check that the value has also been decremented on real cache
        $this->assertEquals(0, $transactionalCache->get('key'));
        $this->assertEquals(0, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testTouch(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $cache->set('key2', 'value2');

        $transactionalCache->touch('key', time() + 2);
        $transactionalCache->touch('key2', time() - 2);

        // expiration times are set on local, but not yet on real cache
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals('value2', $cache->get('key2'));

        $transactionalCache->commit();

        // expiration times have persisted on real cache too
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testFlush(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $transactionalCache->set('key2', 'value2');

        $transactionalCache->flush();

        // check that real cache still isn't flushed, but memory is
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));

        $transactionalCache->commit();

        // everything should be gone by now!
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testRollback(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $transactionalCache->set('key', 'value2');
        $transactionalCache->add('key2', 'value2');

        // something else directly sets the key in the meantime...
        $cache->set('key2', 'value');

        $transactionalCache->commit();

        // both changes should have been "rolled back" and both keys should've
        // been cleared, in both buffered & real cache
        $this->assertEquals(false, $transactionalCache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testNestedTransactionCommit(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        // transaction has already been started in adapterProvider,
        // let's write to it
        $transactionalCache->set('key', 'value');

        // verify that the value has not yet been committed to real cache, but
        // can be read from the transactional layer
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));

        // start a nested transaction & store another value
        $transactionalCache->begin();
        $transactionalCache->set('key2', 'value');

        // verify that none of the values have not yet been committed to real
        // cache, but both can be read from the transactional layer
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals('value', $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));

        // commit nested transaction
        $transactionalCache->commit();

        // verify that none of the values have not yet been committed to real
        // cache, but both can be read from the transactional layer
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals('value', $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));

        // commit parent transaction
        $transactionalCache->commit();

        // verify that all values have persisted to real & can still be read
        // from the transactional layer
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals('value', $transactionalCache->get('key2'));
        $this->assertEquals('value', $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testNestedTransactionRollback(TransactionalStore $transactionalCache, KeyValueStore $cache)
    {
        // transaction has already been started in adapterProvider,
        // let's write to it
        $transactionalCache->set('key', 'value');

        // start a nested transaction & store another value
        $transactionalCache->begin();
        $transactionalCache->set('key2', 'value');

        // we don't need to test values up to this point
        // this has already been tested in testNestedTransactionCommit

        // roll back nested transaction
        $transactionalCache->rollback();

        // verify that the value stored in parent transaction still exists
        // (but hasn't yet been committed to real cache), but that the value
        // in the rolled back transaction is gone)
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));

        // commit parent transaction
        $transactionalCache->commit();

        // verify that the value stored in parent transaction has persisted
        // to real cache, but that the value in the rolled back transaction
        // is gone
        $this->assertEquals('value', $transactionalCache->get('key'));
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals(false, $transactionalCache->get('key2'));
        $this->assertEquals(false, $cache->get('key2'));
    }
}
