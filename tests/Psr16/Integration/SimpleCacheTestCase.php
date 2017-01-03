<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16\Integration;

use ArrayIterator;
use DateInterval;
use Psr\SimpleCache\CacheInterface;

abstract class SimpleCacheTestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @type CacheInterface
     */
    private $cache;

    /**
     * @return CacheInterface that is used in the tests
     */
    abstract public function createSimpleCache();

    protected function setUp()
    {
        $this->cache = $this->createSimpleCache();
    }

    protected function tearDown()
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
    }

    /**
     * A list of invalid keys, all of which should trigger a
     * \Psr\SimpleCache\InvalidArgumentException.
     *
     * Note that these are not *all* the disallowed keys, just a shared base.
     * *Multiple methods treat keys differently than e.g. `set` does (an expects
     * a plain string where the other expects an array of strings)
     * Because of how some methods expect keys in a different way, this is a
     * shared set we can feed into all of them. Some invalid keys have been
     * omitted because they can lead to an invalid array creation (e.g. when an
     * object is used as key of an array) or valid-but-not-intended (e.g. null
     * as array key turns into an empty string) when being used for *Multiple
     * methods.
     *
     * This dataprovider should be used along with invalidSingleKeyProvider,
     * invalidSingleKeyProvider or invalidMultiKeyValueProvider, depending on
     * the method to test invalid input on.
     *
     * @see https://github.com/php-cache/integration-tests/blob/2aafdaf70799736da7d9d3c3d0dd3d05bafdd6f9/src/CachePoolTest.php#L51
     *
     * @return array
     */
    protected static function invalidKeyProvider()
    {
        return [
            [true],
            [false],
            [2],
            [2.5],
            ['{str'],
            ['rand{'],
            ['rand{str'],
            ['rand}str'],
            ['rand(str'],
            ['rand)str'],
            ['rand/str'],
            ['rand\\str'],
            ['rand@str'],
            ['rand:str'],
        ];
    }

    /**
     * Addition to invalidKeyProvider for `get`, `set`, `delete` & `has` (all
     * the methods that take a single key).
     *
     * @return array
     */
    public static function invalidSingleKeyProvider()
    {
        $keys = static::invalidKeyProvider();

        return array_merge($keys, [
            [null],
            [new \stdClass()],
            [['array']],
        ]);
    }

    /**
     * Addition to invalidKeyProvider for `getMultiple` & `deleteMultiple` (all
     * the methods that take an array/Traversable of keys).
     *
     * @return array
     */
    public static function invalidMultiKeyProvider()
    {
        $keys = static::invalidKeyProvider();

        // null & an object are also invalid input...
        $return = array_merge($keys, [
            [null],
            [new \stdClass()],
        ]);

        foreach ($keys as $input) {
            $key = $input[0];
            $return[] = [[$key]];
            $return[] = [new ArrayIterator([$key])];
        }

        return $return;
    }

    /**
     * Addition to invalidKeyProvider for `setMultiple` (all the methods that
     * take an array/Traversable of keys => values).
     *
     * @return array
     */
    public static function invalidMultiKeyValueProvider()
    {
        $keys = static::invalidKeyProvider();

        // null & an object are also invalid input...
        $return = array_merge($keys, [
            [null],
            [new \stdClass()],
        ]);

        foreach ($keys as $input) {
            $key = $input[0];
            $return[] = [[$key => 'value']];
            $return[] = [new ArrayIterator([$key => 'value'])];
        }

        return $return;
    }

    /**
     * Test a basic get & set routine, to confirm basic usage.
     * We'll be relying in `get` & `set` to work correctly in the rest of these
     * tests.
     */
    public function testGetAndSet()
    {
        // test that nothing currently exists for this key
        $result = $this->cache->get('key');
        $this->assertNull($result);

        // assert that value is set successfully
        $result = $this->cache->set('key', 'value');
        $this->assertTrue($result);

        // and check if value was actually set correctly & can be fetched again
        $result = $this->cache->get('key');
        $this->assertSame('value', $result);
    }

    /**
     * Test that retrieving non-existing keys returns `null`.
     */
    public function testGetNonExisting()
    {
        $this->assertNull($this->cache->get('key'));
    }

    /**
     * Test retrieving a default value (for when nothing in cache is found)
     */
    public function testGetDefault()
    {
        $this->assertSame('default', $this->cache->get('key', 'default'));
    }

    /**
     * Test invalid keys for `get`.
     *
     * @dataProvider invalidSingleKeyProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testGetInvalidKey($key)
    {
        $this->cache->get($key);
    }

    /**
     * Test invalid keys for `set`.
     *
     * @dataProvider invalidSingleKeyProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testSetInvalidKey($key)
    {
        $this->cache->set($key, 'value');
    }

    /**
     * Test setting an expired value: it should return a successful operation
     * (true), but the value shouldn't persist (it's expired).
     */
    public function testSetExpired()
    {
        // integer input
        $success = $this->cache->set('key', 'value', time() - 1);
        $this->assertTrue($success);

        // DateInterval input
        $interval = new DateInterval('PT1S');
        $interval->invert = 1;
        $success = $this->cache->set('key2', 'value', $interval);
        $this->assertTrue($success);

        // check if both keys are blank, since they're expired
        $this->assertNull($this->cache->get('key'));
        $this->assertNull($this->cache->get('key2'));
    }

    /**
     * Test setting an expiration time in the future.
     */
    public function testSetFutureExpiration()
    {
        // integer input
        $success = $this->cache->set('key', 'value', time() + 1);
        $this->assertTrue($success);

        // DateInterval input
        $success = $this->cache->set('key2', 'value', new DateInterval('PT1S'));
        $this->assertTrue($success);

        // verify that both have actually been stored
        $this->assertSame('value', $this->cache->get('key'));
        $this->assertSame('value', $this->cache->get('key2'));

        sleep(2);

        // check if both keys are blank, since they're expired
        $this->assertNull($this->cache->get('key'));
        $this->assertNull($this->cache->get('key2'));
    }

    /**
     * Test deleting an existing value.
     */
    public function testDelete()
    {
        // set a value to be deleted
        $this->cache->set('key', 'value');

        $success = $this->cache->delete('key');

        // check to confirm delete
        $this->assertTrue($success);
        $this->assertNull($this->cache->get('key'));
    }

    /**
     * Test deleting a non-existing value. This should still be a successful
     * operation (return value of `true`) because the result is fine: the value
     * is not in cache.
     */
    public function testDeleteNonExisting()
    {
        $success = $this->cache->delete('key');

        // check to confirm delete
        $this->assertTrue($success);
        $this->assertNull($this->cache->get('key'));
    }

    /**
     * Test invalid keys for `delete`.
     *
     * @dataProvider invalidSingleKeyProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testDeleteInvalidKey($key)
    {
        $this->cache->delete($key);
    }

    /**
     * Test flushing the cache.
     */
    public function testClear()
    {
        // set a value to be cleared
        $this->cache->set('key', 'value');

        $success = $this->cache->clear();

        // check to confirm delete
        $this->assertTrue($success);
        $this->assertNull($this->cache->get('key'));
    }

    /**
     * Test getting multiple values from cache, using an array as input.
     */
    public function testGetMultiple()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value');

        $results = $this->cache->getMultiple(['key', 'key2']);
        $this->assertSame(['key' => 'value', 'key2' => 'value'], $results);
    }

    /**
     * Test getting multiple values (including non-existing) from cache, using
     * an array as input.
     */
    public function testGetMultipleIncludingNonExisting()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value');

        $results = $this->cache->getMultiple(['key', 'key2', 'key3']);
        $this->assertSame(['key' => 'value', 'key2' => 'value', 'key3' => null], $results);
    }

    /**
     * Test getting multiple values with a default for non-existing keys, using
     * an array as input.
     */
    public function testGetMultipleDefault()
    {
        $this->cache->set('key', 'value');

        $this->assertSame(
            ['key' => 'value', 'key2' => 'default'],
            $this->cache->getMultiple(['key', 'key2'], 'default')
        );
    }

    /**
     * Test getting multiple values from cache, using a Traversable as input.
     */
    public function testGetMultipleTraversable()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value');

        $iterator = new ArrayIterator(['key', 'key2']);
        $results = $this->cache->getMultiple($iterator);
        $this->assertSame(['key' => 'value', 'key2' => 'value'], $results);
    }

    /**
     * Test getting multiple values (including non-existing) from cache, using a
     * Traversable as input.
     */
    public function testGetMultipleIncludingNonExistingTraversable()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value');

        $iterator = new ArrayIterator(['key', 'key2', 'key3']);
        $results = $this->cache->getMultiple($iterator);
        $this->assertSame(['key' => 'value', 'key2' => 'value', 'key3' => null], $results);
    }

    /**
     * Test getting multiple values with a default for non-existing keys, using
     * a Traversable as input.
     */
    public function testGetMultipleDefaultTraversable()
    {
        $this->cache->set('key', 'value');

        $iterator = new ArrayIterator(['key', 'key2']);
        $this->assertSame(
            ['key' => 'value', 'key2' => 'default'],
            $this->cache->getMultiple($iterator, 'default')
        );
    }

    /**
     * Test invalid keys for `getMultiple`.
     *
     * @dataProvider invalidMultiKeyProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testGetMultipleInvalidKeys($keys)
    {
        $this->cache->getMultiple($keys);
    }

    /**
     * Test setting multiple values, using an array as input.
     */
    public function testSetMultiple()
    {
        $success = $this->cache->setMultiple(['key' => 'value', 'key2' => 'value']);
        $this->assertTrue($success);

        $this->assertSame('value', $this->cache->get('key'));
        $this->assertSame('value', $this->cache->get('key2'));
    }

    /**
     * Test setting multiple values, using a Traversable as input.
     */
    public function testSetMultipleTraversable()
    {
        $iterator = new ArrayIterator(['key' => 'value', 'key2' => 'value']);
        $success = $this->cache->setMultiple($iterator);
        $this->assertTrue($success);

        $this->assertSame('value', $this->cache->get('key'));
        $this->assertSame('value', $this->cache->get('key2'));
    }

    /**
     * Test invalid keys for `setMultiple`.
     *
     * @dataProvider invalidMultiKeyValueProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testSetMultipleInvalidKeys($keys)
    {
        $this->cache->setMultiple($keys);
    }

    /**
     * Test setting an expired value: it should return a successful operation
     * (true), but the value shouldn't persist (it's expired).
     */
    public function testSetMultipleExpired()
    {
        // integer input
        $success = $this->cache->setMultiple(['key' => 'value'], time() - 1);
        $this->assertTrue($success);

        // DateInterval input
        $interval = new DateInterval('PT1S');
        $interval->invert = 1;
        $success = $this->cache->setMultiple(['key2' => 'value'], $interval);
        $this->assertTrue($success);

        // check if both keys are blank, since they're expired
        $this->assertNull($this->cache->get('key'));
        $this->assertNull($this->cache->get('key2'));
    }

    /**
     * Test setting an expiration time in the future.
     */
    public function testSetMultipleFutureExpiration()
    {
        // integer input
        $success = $this->cache->setMultiple(['key' => 'value'], time() + 1);
        $this->assertTrue($success);

        // DateInterval input
        $success = $this->cache->setMultiple(['key2' => 'value'], new DateInterval('PT1S'));
        $this->assertTrue($success);

        // verify that both have actually been stored
        $this->assertSame('value', $this->cache->get('key'));
        $this->assertSame('value', $this->cache->get('key2'));

        sleep(2);

        // check if both keys are blank, since they're expired
        $this->assertNull($this->cache->get('key'));
        $this->assertNull($this->cache->get('key2'));
    }

    /**
     * Test deleting multiple keys, with an array as input.
     */
    public function testDeleteMultiple()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value');

        $success = $this->cache->deleteMultiple(['key', 'key2']);
        $this->assertTrue($success);
        $this->assertNull($this->cache->get('key'));
        $this->assertNull($this->cache->get('key2'));
    }

    /**
     * Test deleting multiple keys, with a traversable as input.
     */
    public function testDeleteMultipleTraversable()
    {
        $this->cache->set('key', 'value');
        $this->cache->set('key2', 'value');

        $iterator = new ArrayIterator(['key', 'key2']);
        $success = $this->cache->deleteMultiple($iterator);
        $this->assertTrue($success);
        $this->assertNull($this->cache->get('key'));
        $this->assertNull($this->cache->get('key2'));
    }

    /**
     * Test invalid keys for `deleteMultiple`.
     *
     * @dataProvider invalidMultiKeyProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testDeleteMultipleInvalidKeys($keys)
    {
        $this->cache->deleteMultiple($keys);
    }

    /**
     * Test checking if a key exists in cache.
     */
    public function testHas()
    {
        $this->cache->set('key', 'value');

        $this->assertTrue($this->cache->has('key'));
    }

    /**
     * Test checking if a key does not exist in cache.
     */
    public function testHasNot()
    {
        $this->assertFalse($this->cache->has('key'));
    }

    /**
     * Test invalid keys for `has`.
     *
     * @dataProvider invalidSingleKeyProvider
     *
     * @expectedException \Psr\SimpleCache\InvalidArgumentException
     */
    public function testHasInvalidKey($key)
    {
        $this->cache->has($key);
    }
}
