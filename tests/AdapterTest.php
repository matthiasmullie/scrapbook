<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;

class AdapterTest extends AdapterProviderTestCase
{
    /**
     * @dataProvider adapterProvider
     */
    public function testGetAndSet(KeyValueStore $cache)
    {
        $return = $cache->set('key', 'value');

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetFail(KeyValueStore $cache)
    {
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetNonReferential(KeyValueStore $cache)
    {
        // this is mostly for MemoryStore - other stores probably aren't at risk

        $object = new \StdClass();
        $object->value = 'test';
        $cache->set('key', $object);

        // clone the object because we'll be messing with it ;)
        $comparison = clone $object;

        // changing the object after it's been cached shouldn't affect cache
        $object->value = 'updated-value';
        $fromCache = $cache->get('key');
        $this->assertEquals($comparison, $fromCache);

        // changing the value we got from cache shouldn't change what's in cache
        $fromCache->value = 'updated-value-2';
        $fromCache2 = $cache->get('key');
        $this->assertNotEquals($comparison, $fromCache);
        $this->assertEquals($comparison, $fromCache2);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetMulti(KeyValueStore $cache)
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        foreach ($items as $key => $value) {
            $cache->set($key, $value);
        }

        $this->assertEquals($items, $cache->getMulti(array_keys($items)));

        // requesting non-existing keys
        $this->assertEquals(array(), $cache->getMulti(array('key3')));
        $this->assertEquals(array('key2' => 'value2'), $cache->getMulti(array('key2', 'key3')));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testGetNoCasTokens(KeyValueStore $cache)
    {
        $cache->get('key', $token);
        $this->assertEquals(null, $token);

        $cache->getMulti(array('key'), $tokens);
        $this->assertEquals(array(), $tokens);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSetExpired(KeyValueStore $cache)
    {
        $return = $cache->set('key', 'value', time() - 2);
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));

        // test if we can add to, but not replace or touch an expired value; it
        // should be treated as if the value doesn't exist)
        $return = $cache->replace('key', 'value');
        $this->assertEquals(false, $return);
        $return = $cache->touch('key', time() + 2);
        $this->assertEquals(false, $return);
        $return = $cache->add('key', 'value');
        $this->assertEquals(true, $return);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSetMulti(KeyValueStore $cache)
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        $return = $cache->setMulti($items);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals('value', $cache->get('key'));
        $this->assertEquals('value2', $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testSetMultiExpired(KeyValueStore $cache)
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        $return = $cache->setMulti($items, time() - 2);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDelete(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        $return = $cache->delete('key');

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));

        // delete non-existing key
        $return = $cache->delete('key2');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDeleteMulti(KeyValueStore $cache)
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        $cache->setMulti($items);
        $return = $cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $cache->get('key2'));

        // delete all non-existing key (they've been deleted already)
        $return = $cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $cache->get('key2'));

        // delete existing & non-existing key
        $cache->set('key', 'value');
        $return = $cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $expect['key'] = true;
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $cache->get('key'));
        $this->assertEquals(false, $cache->get('key2'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testAdd(KeyValueStore $cache)
    {
        $return = $cache->add('key', 'value');

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testAddFail(KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $return = $cache->add('key', 'value-2');

        $this->assertEquals(false, $return);
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testAddExpired(KeyValueStore $cache)
    {
        $return = $cache->add('key', 'value', time() - 2);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplace(KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $return = $cache->replace('key', 'value-2');

        $this->assertEquals(true, $return);
        $this->assertEquals('value-2', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplaceFail(KeyValueStore $cache)
    {
        $return = $cache->replace('key', 'value');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplaceExpired(KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $return = $cache->replace('key', 'value', time() - 2);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testReplaceSameValue(KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $return = $cache->replace('key', 'value');

        $this->assertEquals(true, $return);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCas(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        // token via get()
        $cache->get('key', $token);
        $return = $cache->cas($token, 'key', 'updated-value');

        $this->assertEquals(true, $return);
        $this->assertEquals('updated-value', $cache->get('key'));

        // token via getMulti()
        $cache->getMulti(array('key'), $tokens);
        $token = $tokens['key'];
        $return = $cache->cas($token, 'key', 'updated-value-2');

        $this->assertEquals(true, $return);
        $this->assertEquals('updated-value-2', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasFail(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        // get CAS token
        $cache->get('key', $token);

        // write something else to the same key in the meantime
        $cache->set('key', 'updated-value');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $cache->cas($token, 'key', 'updated-value-2');

        $this->assertEquals(false, $return);
        $this->assertEquals('updated-value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasFail2(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        // get CAS token
        $cache->get('key', $token);

        // delete that key in the meantime
        $cache->delete('key');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $cache->cas($token, 'key', 'updated-value-2');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasExpired(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        // token via get()
        $cache->get('key', $token);
        $return = $cache->cas($token, 'key', 'updated-value', time() - 2);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasSameValue(KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $cache->get('key', $token);
        $return = $cache->cas($token, 'key', 'value');

        $this->assertEquals(true, $return);
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testCasNoOriginalValue(KeyValueStore $cache)
    {
        $cache->get('key', $token);
        $return = $cache->cas($token, 'key', 'value');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testIncrement(KeyValueStore $cache)
    {
        // set initial value
        $return = $cache->increment('key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $cache->get('key'));

        $return = $cache->increment('key2', 1, 0);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $cache->get('key2'));

        // increment
        $return = $cache->increment('key', 1, 1);

        $this->assertEquals(2, $return);
        $this->assertEquals(2, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testIncrementFail(KeyValueStore $cache)
    {
        $return = $cache->increment('key', -1, 0);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));

        $return = $cache->increment('key', 5, -2);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));

        // non-numeric value in cache
        $cache->set('key', 'value');
        $return = $cache->increment('key', 1, 1);
        $this->assertEquals(false, $return);
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testIncrementExpired(KeyValueStore $cache)
    {
        // set initial value
        $return = $cache->increment('key', 1, 1, time() - 2);

        $this->assertEquals(1, $return);
        $this->assertEquals(false, $cache->get('key'));

        // set initial value (not expired) & increment (expired)
        $cache->increment('key', 1, 1);
        $return = $cache->increment('key', 1, 1, time() - 2);

        $this->assertEquals(2, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDecrement(KeyValueStore $cache)
    {
        // set initial value
        $return = $cache->decrement('key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $cache->get('key'));

        $return = $cache->decrement('key2', 1, 0);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $cache->get('key2'));

        // decrement
        $return = $cache->decrement('key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $cache->get('key'));

        // decrement again (can't go below 0)
        $return = $cache->decrement('key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDecrementFail(KeyValueStore $cache)
    {
        $return = $cache->decrement('key', -1, 0);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));

        $return = $cache->decrement('key', 5, -2);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $cache->get('key'));

        // non-numeric value in cache
        $cache->set('key', 'value');
        $return = $cache->increment('key', 1, 1);
        $this->assertEquals(false, $return);
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testDecrementExpired(KeyValueStore $cache)
    {
        // set initial value
        $return = $cache->decrement('key', 1, 1, time() - 2);

        $this->assertEquals(1, $return);
        $this->assertEquals(false, $cache->get('key'));

        // set initial value (not expired) & increment (expired)
        $cache->decrement('key', 1, 1);
        $return = $cache->decrement('key', 1, 1, time() - 2);

        $this->assertEquals(0, $return);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testTouch(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        // not yet expired
        $cache->touch('key', time() + 2);
        $this->assertEquals('value', $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testTouchExpired(KeyValueStore $cache)
    {
        $cache->set('key', 'value');

        // expired
        $cache->touch('key', time() - 2);
        $this->assertEquals(false, $cache->get('key'));
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testFlush(KeyValueStore $cache)
    {
        $cache->set('key', 'value');
        $return = $cache->flush();

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $cache->get('key'));
    }
}
