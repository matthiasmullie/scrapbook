<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

class AdapterTest extends AdapterTestCase
{
    public function testGetAndSet(): void
    {
        $return = $this->cache->set('test key', 'value');

        $this->assertTrue($return);
        $this->assertEquals('value', $this->cache->get('test key'));
    }

    public function testGetVeryLongKeys(): void
    {
        $return = $this->cache->set('this-is-turning-out-to-be-a-rather-unusually-long-key', 'value');
        $this->assertTrue($return);
        $this->assertEquals('value', $this->cache->get('this-is-turning-out-to-be-a-rather-unusually-long-key'));

        $return = $this->cache->set('12345678901234567890123456789012345678901234567890', 'value');
        $this->assertTrue($return);
        $this->assertEquals('value', $this->cache->get('12345678901234567890123456789012345678901234567890'));
    }

    public function testGetFail(): void
    {
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testGetNonReferential(): void
    {
        // this is mostly for MemoryStore - other stores probably aren't at risk

        $object = new \stdClass();
        $object->value = 'test';
        $this->cache->set('test key', $object);

        // clone the object because we'll be messing with it ;)
        $comparison = clone $object;

        // changing the object after it's been cached shouldn't affect cache
        $object->value = 'updated-value';
        $fromCache = $this->cache->get('test key');
        $this->assertEquals($comparison, $fromCache);

        // changing the value we got from cache shouldn't change what's in cache
        $fromCache->value = 'updated-value-2';
        $fromCache2 = $this->cache->get('test key');
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
            $this->cache->set($key, $value);
        }

        $this->assertEquals($items, $this->cache->getMulti(array_keys($items)));

        // requesting non-existing keys
        $this->assertEquals([], $this->cache->getMulti(['key3']));
        $this->assertEquals(['key2' => 'value2'], $this->cache->getMulti(['key2', 'key3']));
    }

    public function testGetNoCasTokens(): void
    {
        $this->cache->get('test key', $token);
        $this->assertNull($token);

        $this->cache->getMulti(['test key'], $tokens);
        $this->assertEquals([], $tokens);
    }

    public function testGetCasTokensFromFalse(): void
    {
        // 'false' is also a value, with a token
        $return = $this->cache->set('test key', false);

        $this->assertTrue($return);

        $this->assertFalse($this->cache->get('test key', $token));
        $this->assertNotNull($token);

        $this->assertEquals(['test key' => false], $this->cache->getMulti(['test key'], $tokens));
        $this->assertNotNull($tokens['test key']);
    }

    public function testGetCasTokensOverridesTokenValue(): void
    {
        $token = 'some-value';
        $tokens = ['some-value'];

        $this->assertFalse($this->cache->get('test key', $token));
        $this->assertNull($token);

        $this->assertEquals([], $this->cache->getMulti(['test key'], $tokens));
        $this->assertEquals([], $tokens);
    }

    public function testSetExpired(): void
    {
        $return = $this->cache->set('test key', 'value', time() - 2);
        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));

        // test if we can add to, but not replace or touch an expired value; it
        // should be treated as if the value doesn't exist)
        $return = $this->cache->replace('test key', 'value');
        $this->assertFalse($return);
        $return = $this->cache->touch('test key', time() + 2);
        $this->assertFalse($return);
        $return = $this->cache->add('test key', 'value');
        $this->assertTrue($return);
    }

    public function testSetMulti(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        $return = $this->cache->setMulti($items);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals('value', $this->cache->get('test key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testSetMultiIntegerKeys(): void
    {
        $items = [
            '0' => 'value',
            '1' => 'value2',
        ];

        $return = $this->cache->setMulti($items);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals('value', $this->cache->get('0'));
        $this->assertEquals('value2', $this->cache->get('1'));
    }

    public function testSetMultiExpired(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        $return = $this->cache->setMulti($items, time() - 2);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->cache->get('test key'));
        $this->assertFalse($this->cache->get('key2'));
    }

    public function testGetAndSetMultiVeryLongKeys(): void
    {
        $items = [
            'this-is-turning-out-to-be-a-rather-unusually-long-key' => 'value',
            '12345678901234567890123456789012345678901234567890' => 'value',
        ];

        $this->cache->setMulti($items);

        $this->assertEquals($items, $this->cache->getMulti(array_keys($items)));
    }

    public function testDelete(): void
    {
        $this->cache->set('test key', 'value');

        $return = $this->cache->delete('test key');

        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));

        // delete non-existing key
        $return = $this->cache->delete('key2');

        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('key2'));
    }

    public function testDeleteMulti(): void
    {
        $items = [
            'test key' => 'value',
            'key2' => 'value2',
        ];

        $this->cache->setMulti($items);
        $return = $this->cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->cache->get('test key'));
        $this->assertFalse($this->cache->get('key2'));

        // delete all non-existing key (they've been deleted already)
        $return = $this->cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->cache->get('test key'));
        $this->assertFalse($this->cache->get('key2'));

        // delete existing & non-existing key
        $this->cache->set('test key', 'value');
        $return = $this->cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $expect['test key'] = true;
        $this->assertEquals($expect, $return);
        $this->assertFalse($this->cache->get('test key'));
        $this->assertFalse($this->cache->get('key2'));
    }

    public function testAdd(): void
    {
        $return = $this->cache->add('test key', 'value');

        $this->assertTrue($return);
        $this->assertEquals('value', $this->cache->get('test key'));
    }

    public function testAddFail(): void
    {
        $this->cache->set('test key', 'value');
        $return = $this->cache->add('test key', 'value-2');

        $this->assertFalse($return);
        $this->assertEquals('value', $this->cache->get('test key'));
    }

    public function testAddExpired(): void
    {
        $return = $this->cache->add('test key', 'value', time() - 2);

        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testReplace(): void
    {
        $this->cache->set('test key', 'value');
        $return = $this->cache->replace('test key', 'value-2');

        $this->assertTrue($return);
        $this->assertEquals('value-2', $this->cache->get('test key'));
    }

    public function testReplaceFail(): void
    {
        $return = $this->cache->replace('test key', 'value');

        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testReplaceExpired(): void
    {
        $this->cache->set('test key', 'value');
        $return = $this->cache->replace('test key', 'value', time() - 2);

        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testReplaceSameValue(): void
    {
        $this->cache->set('test key', 'value');
        $return = $this->cache->replace('test key', 'value');

        $this->assertTrue($return);
    }

    public function testCas(): void
    {
        $this->cache->set('test key', 'value');

        // token via get()
        $this->cache->get('test key', $token);
        $return = $this->cache->cas($token, 'test key', 'updated-value');

        $this->assertTrue($return);
        $this->assertEquals('updated-value', $this->cache->get('test key'));

        // token via getMulti()
        $this->cache->getMulti(['test key'], $tokens);
        $token = $tokens['test key'];
        $return = $this->cache->cas($token, 'test key', 'updated-value-2');

        $this->assertTrue($return);
        $this->assertEquals('updated-value-2', $this->cache->get('test key'));
    }

    public function testCasFail(): void
    {
        $this->cache->set('test key', 'value');

        // get CAS token
        $this->cache->get('test key', $token);

        // write something else to the same key in the meantime
        $this->cache->set('test key', 'updated-value');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $this->cache->cas($token, 'test key', 'updated-value-2');

        $this->assertFalse($return);
        $this->assertEquals('updated-value', $this->cache->get('test key'));
    }

    public function testCasFail2(): void
    {
        $this->cache->set('test key', 'value');

        // get CAS token
        $this->cache->get('test key', $token);

        // delete that key in the meantime
        $this->cache->delete('test key');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $this->cache->cas($token, 'test key', 'updated-value-2');

        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testCasExpired(): void
    {
        $this->cache->set('test key', 'value');

        // token via get()
        $this->cache->get('test key', $token);
        $return = $this->cache->cas($token, 'test key', 'updated-value', time() - 2);

        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testCasSameValue(): void
    {
        $this->cache->set('test key', 'value');
        $this->cache->get('test key', $token);
        $return = $this->cache->cas($token, 'test key', 'value');

        $this->assertTrue($return);
    }

    public function testCasNoOriginalValue(): void
    {
        $this->cache->get('test key', $token);
        $return = $this->cache->cas($token, 'test key', 'value');

        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testIncrement(): void
    {
        // set initial value
        $return = $this->cache->increment('test key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $this->cache->get('test key'));

        $return = $this->cache->increment('key2', 1, 0);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->cache->get('key2'));

        // increment
        $return = $this->cache->increment('test key', 1, 1);

        $this->assertEquals(2, $return);
        $this->assertEquals(2, $this->cache->get('test key'));
    }

    public function testIncrementFail(): void
    {
        $return = $this->cache->increment('test key', -1, 0);
        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));

        $return = $this->cache->increment('test key', 5, -2);
        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));

        // non-numeric value in cache
        $this->cache->set('test key', 'value');
        $return = $this->cache->increment('test key', 1, 1);
        $this->assertFalse($return);
        $this->assertEquals('value', $this->cache->get('test key'));
    }

    public function testIncrementExpired(): void
    {
        // set initial value
        $return = $this->cache->increment('test key', 1, 1, time() - 2);

        $this->assertEquals(1, $return);
        $this->assertFalse($this->cache->get('test key'));

        // set initial value (not expired) & increment (expired)
        $this->cache->increment('test key', 1, 1);
        $return = $this->cache->increment('test key', 1, 1, time() - 2);

        $this->assertEquals(2, $return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testDecrement(): void
    {
        // set initial value
        $return = $this->cache->decrement('test key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $this->cache->get('test key'));

        $return = $this->cache->decrement('key2', 1, 0);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->cache->get('key2'));

        // decrement
        $return = $this->cache->decrement('test key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->cache->get('test key'));

        // decrement again (can't go below 0)
        $return = $this->cache->decrement('test key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->cache->get('test key'));
    }

    public function testDecrementFail(): void
    {
        $return = $this->cache->decrement('test key', -1, 0);
        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));

        $return = $this->cache->decrement('test key', 5, -2);
        $this->assertFalse($return);
        $this->assertFalse($this->cache->get('test key'));

        // non-numeric value in cache
        $this->cache->set('test key', 'value');
        $return = $this->cache->increment('test key', 1, 1);
        $this->assertFalse($return);
        $this->assertEquals('value', $this->cache->get('test key'));
    }

    public function testDecrementExpired(): void
    {
        // set initial value
        $return = $this->cache->decrement('test key', 1, 1, time() - 2);

        $this->assertEquals(1, $return);
        $this->assertFalse($this->cache->get('test key'));

        // set initial value (not expired) & increment (expired)
        $this->cache->decrement('test key', 1, 1);
        $return = $this->cache->decrement('test key', 1, 1, time() - 2);

        $this->assertEquals(0, $return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testTouch(): void
    {
        $this->cache->set('test key', 'value');

        // not yet expired
        $return = $this->cache->touch('test key', time() + 2);
        $this->assertTrue($return);
        $this->assertEquals('value', $this->cache->get('test key'));
    }

    public function testTouchExpired(): void
    {
        $this->cache->set('test key', 'value');

        // expired
        $return = $this->cache->touch('test key', time() - 2);
        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testFlush(): void
    {
        $this->cache->set('test key', 'value');
        $return = $this->cache->flush();

        $this->assertTrue($return);
        $this->assertFalse($this->cache->get('test key'));
    }

    public function testCollectionGetParentKey(): void
    {
        $collection = $this->cache->getCollection($this->collectionName);

        $this->cache->set('key', 'value');

        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertFalse($collection->get('key'));
    }

    public function testCollectionGetCollectionKey(): void
    {
        $collection = $this->cache->getCollection($this->collectionName);

        $collection->set('key', 'value');

        $this->assertFalse($this->cache->get('key'));
        $this->assertEquals('value', $collection->get('key'));

        $collection->flush();
    }

    public function testCollectionSetSameKey(): void
    {
        $collection = $this->cache->getCollection($this->collectionName);

        $this->cache->set('key', 'value');
        $collection->set('key', 'other-value');

        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('other-value', $collection->get('key'));

        $collection->flush();
    }

    public function testCollectionFlushParent(): void
    {
        $collection = $this->cache->getCollection($this->collectionName);

        $this->cache->set('key', 'value');
        $collection->set('key', 'other-value');

        $this->cache->flush();

        $this->assertFalse($this->cache->get('key'));
        $this->assertFalse($collection->get('key'));

        $collection->flush();
    }

    public function testCollectionFlushCollection(): void
    {
        $collection = $this->cache->getCollection($this->collectionName);

        $this->cache->set('key', 'value');
        $collection->set('key', 'other-value');

        $collection->flush();

        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertFalse($collection->get('key'));
    }
}
