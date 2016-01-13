<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\Adapters\AdapterInterface;
use MatthiasMullie\Scrapbook\Tests\Adapters\AdapterStub;
use PHPUnit_Framework_TestCase;
use PHPUnit_Framework_TestSuite;
use ReflectionClass;

class AdapterProvider
{
    /**
     * @var KeyValueStore[]
     */
    protected static $adapters = array();

    /**
     * @var PHPUnit_Framework_TestCase
     */
    protected $testCase;

    /**
     * @param PHPUnit_Framework_TestCase $testCase
     */
    public function __construct(PHPUnit_Framework_TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * @return PHPUnit_Framework_TestSuite
     */
    public function getSuite()
    {
        $suite = new \PHPUnit_Framework_TestSuite('Test integration');

        $i = 0;
        foreach ($this->getAdapters() as $name => $adapter) {
            $class = new ReflectionClass(get_class($this->testCase));
            $tests = new PHPUnit_Framework_TestSuite($class);

            // we can't use --filter to narrow down on specific adapters
            // (because we're not using dataProvider), but we can make sure it's
            // properly split up into groups & then use --group
            static::injectGroup($tests, $name);

            // and let's make sure to inject the specific adapter into the test
            static::injectAdapter($tests, $adapter);

            // let's add all of the integrations tests for every adapter
            $suite->addTest($tests);

            $i++;
        }

        return $suite;
    }

    /**
     * Injecting an adapter must be done recursively: there are some methods
     * that get input from dataProviders, so they're wrapped in another class
     * that we must unwrap in order to assign the adapter.
     *
     * @param \PHPUnit_Framework_TestSuite $suite
     * @param KeyValueStore $adapter
     */
    protected function injectAdapter(\PHPUnit_Framework_TestSuite $suite, KeyValueStore $adapter)
    {
        foreach ($suite as $test)
        {
            if ($test instanceof \PHPUnit_Framework_TestSuite) {
                $this->injectAdapter($test, $adapter);
            } else {
                $test->setAdapter($adapter);
            }
        }
    }

    /**
     * Because some tests are wrapped inside a dataProvider suite, we need to
     * make sure that the groups are recursively assigned to each suite until we
     * reach the child.
     *
     * @param \PHPUnit_Framework_TestSuite $suite
     * @param string$group
     */
    protected function injectGroup(\PHPUnit_Framework_TestSuite $suite, $group)
    {
        $tests = $suite->tests();
        $suite->setGroupDetails(array('default' => $tests, $group => $tests));

        foreach ($suite->tests() as $test)
        {
            if ($test instanceof \PHPUnit_Framework_TestSuite) {
                $this->injectGroup($test, $group);
            }
        }
    }

    /**
     * @return KeyValueStore[]
     */
    public function getAdapters()
    {
        // re-use adapters across tests - if we keep initializing clients, they
        // may fail because of too much connections (and it's just overhead...)
        if (static::$adapters) {
            return static::$adapters;
        }

        $adapters = $this->getAllAdapters();
        foreach ($adapters as $class) {
            try {
                /** @var AdapterInterface $adapter */
                $fqcn = "\\MatthiasMullie\\Scrapbook\\Tests\\Adapters\\{$class}Test";
                $adapter = new $fqcn();

                static::$adapters[$class] = $adapter->get();
            } catch (\Exception $e) {
                static::$adapters[$class] = new AdapterStub($e);
            }
        }

        return static::$adapters;
    }

    /**
     * @return string[]
     */
    protected function getAllAdapters()
    {
        $files = glob(__DIR__.'/Adapters/*Test.php');

        // since we're PSR-4, just stripping .php from the filename = classnames
        // also strip "Test" suffix, which will again be appended later
        $adapters = array_map(function ($file) {
            return basename($file, 'Test.php');
        }, $files);

        return $adapters;
    }
}
