<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;

abstract class AbstractKeyValueStoreTestCase extends AbstractAdapterTestCase
{
    protected KeyValueStore $testKeyValueStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testKeyValueStore = $this->getTestKeyValueStore($this->adapterKeyValueStore);
        $this->testKeyValueStore->flush();
    }

    public function getTestKeyValueStore(KeyValueStore $keyValueStore): KeyValueStore
    {
        // for the main KeyValueStore tests, the adapter to be tested
        // is simply the original adapter
        return $keyValueStore;
    }

    public function testGetAndSet(): void
    {
        $return = $this->testKeyValueStore->set('test key', 'value');

        $this->assertTrue($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
    }

    public function testGetVeryLongKeys(): void
    {
        $return = $this->testKeyValueStore->set('this-is-turning-out-to-be-a-rather-unusually-long-key', 'value');
        $this->assertTrue($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('this-is-turning-out-to-be-a-rather-unusually-long-key'));

        $return = $this->testKeyValueStore->set('12345678901234567890123456789012345678901234567890', 'value');
        $this->assertTrue($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('12345678901234567890123456789012345678901234567890'));
    }

    public function testGetFail(): void
    {
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testGetNonReferential(): void
    {
        // this is mostly for MemoryStore - other stores probably aren't at risk

        $object = new \stdClass();
        $object->value = 'test';
        $this->testKeyValueStore->set('test key', $object);

        // clone the object because we'll be messing with it ;)
        $comparison = clone $object;

        // changing the object after it's been cached shouldn't affect cache
        $object->value = 'updated-value';
        $fromCache = $this->testKeyValueStore->get('test key');
        $this->assertEquals($comparison, $fromCache);

        // changing the value we got from cache shouldn't change what's in cache
        $fromCache->value = 'updated-value-2';
        $fromCache2 = $this->testKeyValueStore->get('test key');
        $this->assertNotEquals($comparison, $fromCache);
        $this->assertEquals($comparison, $fromCache2);
    }

    public function testGetMulti(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        foreach ($items as $key => $value) {
            $this->testKeyValueStore->set($key, $value);
        }

        $this->assertEquals($items, $this->testKeyValueStore->getMulti(array_keys($items)));

        // requesting non-existing keys
        $this->assertEquals([], $this->testKeyValueStore->getMulti(['key3']));
        $this->assertEquals(['key2' => 'value2'], $this->testKeyValueStore->getMulti(['key2', 'key3']));
    }

    public function testGetNoCasTokens(): void
    {
        $this->testKeyValueStore->get('test key', $token);
        $this->assertNull($token);

        $this->testKeyValueStore->getMulti(['test key'], $tokens);
        $this->assertEquals([], $tokens);
    }

    public function testGetCasTokensFromFalse(): void
    {
        // 'false' is also a value, with a token
        $return = $this->testKeyValueStore->set('test key', false);

        $this->assertTrue($return);

        $this->assertFalse($this->testKeyValueStore->get('test key', $token));
        $this->assertNotNull($token);

        $this->assertEquals(['test key' => false], $this->testKeyValueStore->getMulti(['test key'], $tokens));
        $this->assertNotNull($tokens['test key']);
    }

    public function testGetCasTokensOverridesTokenValue(): void
    {
        $token = 'some-value';
        $tokens = ['some-value'];

        $this->assertFalse($this->testKeyValueStore->get('test key', $token));
        $this->assertNull($token);

        $this->assertEquals([], $this->testKeyValueStore->getMulti(['test key'], $tokens));
        $this->assertEquals([], $tokens);
    }

    public function testSetExpired(): void
    {
        $return = $this->testKeyValueStore->set('test key', 'value', time() - 2);
        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        // test if we can add to, but not replace or touch an expired value; it
        // should be treated as if the value doesn't exist)
        $return = $this->testKeyValueStore->replace('test key', 'value');
        $this->assertFalse($return);
        $return = $this->testKeyValueStore->touch('test key', time() + 2);
        $this->assertFalse($return);
        $return = $this->testKeyValueStore->add('test key', 'value');
        $this->assertTrue($return);
    }

    public function testSetMulti(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        $return = $this->testKeyValueStore->setMulti($items);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
        $this->assertEquals('value2', $this->testKeyValueStore->get('key2'));
    }

    public function testSetMultiIntegerKeys(): void
    {
        $items = [
            '0' => 'value',
            '1' => 'value2',
        ];

        $return = $this->testKeyValueStore->setMulti($items);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals('value', $this->testKeyValueStore->get('0'));
        $this->assertEquals('value2', $this->testKeyValueStore->get('1'));
    }

    public function testSetMultiExpired(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        $return = $this->testKeyValueStore->setMulti($items, time() - 2);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
    }

    public function testGetAndSetMultiVeryLongKeys(): void
    {
        $items = [
            'this-is-turning-out-to-be-a-rather-unusually-long-key' => 'value',
            '12345678901234567890123456789012345678901234567890' => 'value',
        ];

        $this->testKeyValueStore->setMulti($items);

        $this->assertEquals($items, $this->testKeyValueStore->getMulti(array_keys($items)));
    }

    public function testDelete(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        $return = $this->testKeyValueStore->delete('test key');

        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        // delete non-existing key
        $return = $this->testKeyValueStore->delete('key2');

        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('key2'));
    }

    public function testDeleteMulti(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        $this->testKeyValueStore->setMulti($items);
        $return = $this->testKeyValueStore->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));

        // delete all non-existing key (they've been deleted already)
        $return = $this->testKeyValueStore->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));

        // delete existing & non-existing key
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $expect['test key'] = true;
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
        $this->assertFalse($this->testKeyValueStore->get('key2'));
    }

    public function testAdd(): void
    {
        $return = $this->testKeyValueStore->add('test key', 'value');

        $this->assertTrue($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
    }

    public function testAddFail(): void
    {
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->add('test key', 'value-2');

        $this->assertFalse($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
    }

    public function testAddExpired(): void
    {
        $return = $this->testKeyValueStore->add('test key', 'value', time() - 2);

        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testReplace(): void
    {
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->replace('test key', 'value-2');

        $this->assertTrue($return);
        $this->assertEquals('value-2', $this->testKeyValueStore->get('test key'));
    }

    public function testReplaceFail(): void
    {
        $return = $this->testKeyValueStore->replace('test key', 'value');

        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testReplaceExpired(): void
    {
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->replace('test key', 'value', time() - 2);

        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testReplaceSameValue(): void
    {
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->replace('test key', 'value');

        $this->assertTrue($return);
    }

    public function testCas(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        // token via get()
        $this->testKeyValueStore->get('test key', $token);
        $return = $this->testKeyValueStore->cas($token, 'test key', 'updated-value');

        $this->assertTrue($return);
        $this->assertEquals('updated-value', $this->testKeyValueStore->get('test key'));

        // token via getMulti()
        $this->testKeyValueStore->getMulti(['test key'], $tokens);
        $token = $tokens['test key'];
        $return = $this->testKeyValueStore->cas($token, 'test key', 'updated-value-2');

        $this->assertTrue($return);
        $this->assertEquals('updated-value-2', $this->testKeyValueStore->get('test key'));
    }

    public function testCasFail(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        // get CAS token
        $this->testKeyValueStore->get('test key', $token);

        // write something else to the same key in the meantime
        $this->testKeyValueStore->set('test key', 'updated-value');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $this->testKeyValueStore->cas($token, 'test key', 'updated-value-2');

        $this->assertFalse($return);
        $this->assertEquals('updated-value', $this->testKeyValueStore->get('test key'));
    }

    public function testCasFail2(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        // get CAS token
        $this->testKeyValueStore->get('test key', $token);

        // delete that key in the meantime
        $this->testKeyValueStore->delete('test key');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $this->testKeyValueStore->cas($token, 'test key', 'updated-value-2');

        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testCasExpired(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        // token via get()
        $this->testKeyValueStore->get('test key', $token);
        $return = $this->testKeyValueStore->cas($token, 'test key', 'updated-value', time() - 2);

        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testCasSameValue(): void
    {
        $this->testKeyValueStore->set('test key', 'value');
        $this->testKeyValueStore->get('test key', $token);
        $return = $this->testKeyValueStore->cas($token, 'test key', 'value');

        $this->assertTrue($return);
    }

    public function testCasNoOriginalValue(): void
    {
        $this->testKeyValueStore->get('test key', $token);
        $return = $this->testKeyValueStore->cas($token, 'test key', 'value');

        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testIncrement(): void
    {
        // set initial value
        $return = $this->testKeyValueStore->increment('test key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $this->testKeyValueStore->get('test key'));

        $return = $this->testKeyValueStore->increment('key2', 1, 0);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->testKeyValueStore->get('key2'));

        // increment
        $return = $this->testKeyValueStore->increment('test key', 1, 1);

        $this->assertEquals(2, $return);
        $this->assertEquals(2, $this->testKeyValueStore->get('test key'));
    }

    public function testIncrementFail(): void
    {
        $return = $this->testKeyValueStore->increment('test key', -1, 0);
        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        $return = $this->testKeyValueStore->increment('test key', 5, -2);
        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        // non-numeric value in cache
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->increment('test key', 1, 1);
        $this->assertFalse($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
    }

    public function testIncrementExpired(): void
    {
        // set initial value
        $return = $this->testKeyValueStore->increment('test key', 1, 1, time() - 2);

        $this->assertEquals(1, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        // set initial value (not expired) & increment (expired)
        $this->testKeyValueStore->increment('test key', 1, 1);
        $return = $this->testKeyValueStore->increment('test key', 1, 1, time() - 2);

        $this->assertEquals(2, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testDecrement(): void
    {
        // set initial value
        $return = $this->testKeyValueStore->decrement('test key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $this->testKeyValueStore->get('test key'));

        $return = $this->testKeyValueStore->decrement('key2', 1, 0);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->testKeyValueStore->get('key2'));

        // decrement
        $return = $this->testKeyValueStore->decrement('test key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->testKeyValueStore->get('test key'));

        // decrement again (can't go below 0)
        $return = $this->testKeyValueStore->decrement('test key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->testKeyValueStore->get('test key'));
    }

    public function testDecrementFail(): void
    {
        $return = $this->testKeyValueStore->decrement('test key', -1, 0);
        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        $return = $this->testKeyValueStore->decrement('test key', 5, -2);
        $this->assertFalse($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        // non-numeric value in cache
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->increment('test key', 1, 1);
        $this->assertFalse($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
    }

    public function testDecrementExpired(): void
    {
        // set initial value
        $return = $this->testKeyValueStore->decrement('test key', 1, 1, time() - 2);

        $this->assertEquals(1, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));

        // set initial value (not expired) & increment (expired)
        $this->testKeyValueStore->decrement('test key', 1, 1);
        $return = $this->testKeyValueStore->decrement('test key', 1, 1, time() - 2);

        $this->assertEquals(0, $return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testTouch(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        // not yet expired
        $return = $this->testKeyValueStore->touch('test key', time() + 2);
        $this->assertTrue($return);
        $this->assertEquals('value', $this->testKeyValueStore->get('test key'));
    }

    public function testTouchExpired(): void
    {
        $this->testKeyValueStore->set('test key', 'value');

        // expired
        $return = $this->testKeyValueStore->touch('test key', time() - 2);
        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testFlush(): void
    {
        $this->testKeyValueStore->set('test key', 'value');
        $return = $this->testKeyValueStore->flush();

        $this->assertTrue($return);
        $this->assertFalse($this->testKeyValueStore->get('test key'));
    }

    public function testCollectionGetParentKey(): void
    {
        $collection = $this->testKeyValueStore->getCollection($this->collectionName);

        $this->testKeyValueStore->set('key', 'value');

        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($collection->get('key'));
    }

    public function testCollectionGetCollectionKey(): void
    {
        $collection = $this->testKeyValueStore->getCollection($this->collectionName);

        $collection->set('key', 'value');

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertEquals('value', $collection->get('key'));

        $collection->flush();
    }

    public function testCollectionSetSameKey(): void
    {
        $collection = $this->testKeyValueStore->getCollection($this->collectionName);

        $this->testKeyValueStore->set('key', 'value');
        $collection->set('key', 'other-value');

        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertEquals('other-value', $collection->get('key'));

        $collection->flush();
    }

    public function testCollectionFlushParent(): void
    {
        $collection = $this->testKeyValueStore->getCollection($this->collectionName);

        $this->testKeyValueStore->set('key', 'value');
        $collection->set('key', 'other-value');

        $this->testKeyValueStore->flush();

        $this->assertFalse($this->testKeyValueStore->get('key'));
        $this->assertFalse($collection->get('key'));

        $collection->flush();
    }

    public function testCollectionFlushCollection(): void
    {
        $collection = $this->testKeyValueStore->getCollection($this->collectionName);

        $this->testKeyValueStore->set('key', 'value');
        $collection->set('key', 'other-value');

        $collection->flush();

        $this->assertEquals('value', $this->testKeyValueStore->get('key'));
        $this->assertFalse($collection->get('key'));
    }

    public function testPermanentExpiration(): void
    {
        $return = $this->testKeyValueStore->set('set', 'value', 0);
        $this->assertTrue($return);

        $return = $this->testKeyValueStore->setMulti(['setMulti' => 'value'], 0);
        $this->assertEquals(['setMulti' => true], $return);

        $return = $this->testKeyValueStore->add('add', 'value', 0);
        $this->assertTrue($return);

        $this->testKeyValueStore->set('replace', 'something-else', 0);
        $return = $this->testKeyValueStore->replace('replace', 'value', 0);
        $this->assertTrue($return);

        $this->testKeyValueStore->set('cas', 'something-else', 0);
        $this->testKeyValueStore->get('cas', $token);
        $return = $this->testKeyValueStore->cas($token, 'cas', 'value', 0);
        $this->assertTrue($return);

        $return = $this->testKeyValueStore->increment('increment', 1, 1, 0);
        $this->assertEquals(1, $return);

        $return = $this->testKeyValueStore->decrement('decrement', 1, 1, 0);
        $this->assertEquals(1, $return);

        sleep(2);

        // confirm that data is still there, and an expiration of 0 was not
        // interpreted to mean "expire this second"
        $this->assertEquals('value', $this->testKeyValueStore->get('set'));
        $this->assertEquals('value', $this->testKeyValueStore->get('setMulti'));
        $this->assertEquals('value', $this->testKeyValueStore->get('add'));
        $this->assertEquals('value', $this->testKeyValueStore->get('replace'));
        $this->assertEquals('value', $this->testKeyValueStore->get('cas'));
        $this->assertEquals(1, $this->testKeyValueStore->get('increment'));
        $this->assertEquals(1, $this->testKeyValueStore->get('decrement'));
    }
}
