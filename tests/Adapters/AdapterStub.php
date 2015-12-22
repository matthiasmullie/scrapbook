<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit_Framework_TestCase;

/**
 * If an adapter fails to initialize, we'll want to proceed with the tests
 * anyway, just add tests for this particular adapter as fixed.
 */
class AdapterStub implements KeyValueStore
{
    /**
     * @var PHPUnit_Framework_TestCase
     */
    protected $test;

    /**
     * @var Exception
     */
    protected $exception;

    /**
     * @param PHPUnit_Framework_TestCase $test
     * @param Exception $exception
     */
    public function __construct(PHPUnit_Framework_TestCase $test, Exception $exception)
    {
        $this->test = $test;
        $this->exception = $exception;
    }

    public function get($key, &$token = null)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function getMulti(array $keys, array &$tokens = null)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function set($key, $value, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function setMulti(array $items, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function delete($key)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function deleteMulti(array $keys)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function add($key, $value, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function replace($key, $value, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function cas($token, $key, $value, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function touch($key, $expire)
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }

    public function flush()
    {
        $this->test->markTestIncomplete($this->exception->getMessage());
    }
}
