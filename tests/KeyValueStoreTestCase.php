<?php
namespace Scrapbook\Cache\Tests;

use Scrapbook\Cache\KeyValueStore;
use PHPUnit_Framework_TestCase;

abstract class KeyValueStoreTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var KeyValueStore
     */
    protected $cache;

    /**
     * @return KeyValueStore
     */
    abstract protected function getStore();

    protected function setUp()
    {
        parent::setUp();

        $this->cache = $this->getStore();
    }

    protected function tearDown()
    {
        if ($this->cache) {
            $this->cache->flush();
        }

        parent::tearDown();
    }

    public function testGetAndSet()
    {
        $return = $this->cache->set('key', 'value');

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testGetFail()
    {
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testGetNonReferential()
    {
        // this is mostly for MemoryStore - other stores probably aren't at risk

        $object = new \StdClass();
        $object->value = 'test';
        $this->cache->set('key', $object);

        // clone the object because we'll be messing with it ;)
        $comparison = clone $object;

        // changing the object after it's been cached shouldn't affect cache
        $object->value = 'updated-value';
        $fromCache = $this->cache->get('key');
        $this->assertEquals($comparison, $fromCache);

        // changing the value we got from cache shouldn't change what's in cache
        $fromCache->value = 'updated-value-2';
        $fromCache2 = $this->cache->get('key');
        $this->assertNotEquals($comparison, $fromCache);
        $this->assertEquals($comparison, $fromCache2);
    }

    public function testGetMulti()
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        foreach ($items as $key => $value) {
            $this->cache->set($key, $value);
        }

        $this->assertEquals($items, $this->cache->getMulti(array_keys($items)));

        // requesting non-existing keys
        $this->assertEquals(array(), $this->cache->getMulti(array('key3')));
        $this->assertEquals(array('key2' => 'value2'), $this->cache->getMulti(array('key2', 'key3')));
    }

    public function testSetExpired()
    {
        $return = $this->cache->set('key', 'value', time() - 1);
        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        // test if we can add to, but not replace or touch an expired value; it
        // should be treated as if the value doesn't exist)
        $return = $this->cache->replace('key', 'value');
        $this->assertEquals($return, false);
        $return = $this->cache->touch('key', time() + 1);
        $this->assertEquals($return, false);
        $return = $this->cache->add('key', 'value');
        $this->assertEquals($return, true);
    }

    public function testSetMulti()
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        $return = $this->cache->setMulti($items);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals('value', $this->cache->get('key'));
        $this->assertEquals('value2', $this->cache->get('key2'));
    }

    public function testSetMultiExpired()
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        $return = $this->cache->setMulti($items, time() - 1);

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testDelete()
    {
        $this->cache->set('key', 'value');

        $return = $this->cache->delete('key');

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        // delete non-existing key
        $return = $this->cache->delete('key2');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testDeleteMulti()
    {
        $items = array(
            'key' => 'value',
            'key2' => 'value2',
        );

        $this->cache->setMulti($items);
        $return = $this->cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), true);
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->cache->get('key2'));

        // delete all non-existing key (they've been deleted already)
        $return = $this->cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->cache->get('key2'));

        // delete existing & non-existing key
        $this->cache->set('key', 'value');
        $return = $this->cache->deleteMulti(array_keys($items));

        $expect = array_fill_keys(array_keys($items), false);
        $expect['key'] = true;
        $this->assertEquals($expect, $return);
        $this->assertEquals(false, $this->cache->get('key'));
        $this->assertEquals(false, $this->cache->get('key2'));
    }

    public function testAdd()
    {
        $return = $this->cache->add('key', 'value');

        $this->assertEquals(true, $return);
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testAddFail()
    {
        $this->cache->set('key', 'value');
        $return = $this->cache->add('key', 'value-2');

        $this->assertEquals(false, $return);
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testAddExpired()
    {
        $return = $this->cache->add('key', 'value', time() - 1);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testReplace()
    {
        $this->cache->set('key', 'value');
        $return = $this->cache->replace('key', 'value-2');

        $this->assertEquals(true, $return);
        $this->assertEquals('value-2', $this->cache->get('key'));
    }

    public function testReplaceFail()
    {
        $return = $this->cache->replace('key', 'value');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testReplaceExpired()
    {
        $this->cache->set('key', 'value');
        $return = $this->cache->replace('key', 'value', time() - 1);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testCas()
    {
        $this->cache->set('key', 'value');

        // token via get()
        $this->cache->get('key', $token);
        $return = $this->cache->cas($token, 'key', 'updated-value');

        $this->assertEquals(true, $return);
        $this->assertEquals('updated-value', $this->cache->get('key'));

        // token via getMulti()
        $this->cache->getMulti(array('key'), $tokens);
        $token = $tokens['key'];
        $return = $this->cache->cas($token, 'key', 'updated-value-2');

        $this->assertEquals(true, $return);
        $this->assertEquals('updated-value-2', $this->cache->get('key'));
    }

    public function testCasFail()
    {
        $this->cache->set('key', 'value');

        // get CAS token
        $this->cache->get('key', $token);

        // write something else to the same key in the meantime
        $this->cache->set('key', 'updated-value');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $this->cache->cas($token, 'key', 'updated-value-2');

        $this->assertEquals(false, $return);
        $this->assertEquals('updated-value', $this->cache->get('key'));
    }

    public function testCasFail2()
    {
        $this->cache->set('key', 'value');

        // get CAS token
        $this->cache->get('key', $token);

        // delete that key in the meantime
        $this->cache->delete('key');

        // attempt CAS, which should now fail (token no longer valid)
        $return = $this->cache->cas($token, 'key', 'updated-value-2');

        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testCasExpired()
    {
        $this->cache->set('key', 'value');

        // token via get()
        $this->cache->get('key', $token);
        $return = $this->cache->cas($token, 'key', 'updated-value', time() - 1);

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testIncrement()
    {
        // set initial value
        $return = $this->cache->increment('key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $this->cache->get('key'));

        // increment
        $return = $this->cache->increment('key', 1, 1);

        $this->assertEquals(2, $return);
        $this->assertEquals(2, $this->cache->get('key'));
    }

    public function testIncrementFail()
    {
        $return = $this->cache->increment('key', -1, 0);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        $return = $this->cache->increment('key', 5, -2);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        // non-numeric value in cache
        $this->cache->set('key', 'value');
        $return = $this->cache->increment('key', 1, 1);
        $this->assertEquals(false, $return);
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testIncrementExpired()
    {
        // set initial value
        $return = $this->cache->increment('key', 1, 1, time() - 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        // set initial value (not expired) & increment (expired)
        $this->cache->increment('key', 1, 1);
        $return = $this->cache->increment('key', 1, 1, time() - 1);

        $this->assertEquals(2, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testDecrement()
    {
        // set initial value
        $return = $this->cache->decrement('key', 1, 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(1, $this->cache->get('key'));

        // decrement
        $return = $this->cache->decrement('key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->cache->get('key'));

        // decrement again (can't go below 0)
        $return = $this->cache->decrement('key', 1, 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(0, $this->cache->get('key'));
    }

    public function testDecrementFail()
    {
        $return = $this->cache->decrement('key', -1, 0);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        $return = $this->cache->decrement('key', 5, -2);
        $this->assertEquals(false, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        // non-numeric value in cache
        $this->cache->set('key', 'value');
        $return = $this->cache->increment('key', 1, 1);
        $this->assertEquals(false, $return);
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testDecrementExpired()
    {
        // set initial value
        $return = $this->cache->decrement('key', 1, 1, time() - 1);

        $this->assertEquals(1, $return);
        $this->assertEquals(false, $this->cache->get('key'));

        // set initial value (not expired) & increment (expired)
        $this->cache->decrement('key', 1, 1);
        $return = $this->cache->decrement('key', 1, 1, time() - 1);

        $this->assertEquals(0, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testTouch()
    {
        $this->cache->set('key', 'value');

        // not yet expired
        $this->cache->touch('key', time() + 1);
        $this->assertEquals('value', $this->cache->get('key'));
    }

    public function testTouchExpired()
    {
        $this->cache->set('key', 'value');

        // expired
        $this->cache->touch('key', time() - 1);
        $this->assertEquals(false, $this->cache->get('key'));
    }

    public function testFlush()
    {
        $this->cache->set('key', 'value');
        $return = $this->cache->flush();

        $this->assertEquals(true, $return);
        $this->assertEquals(false, $this->cache->get('key'));
    }
}
