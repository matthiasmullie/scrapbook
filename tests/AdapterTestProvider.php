<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;

class AdapterTestProvider
{
    /**
     * @var KeyValueStore[]
     */
    protected static $adapters = array();

    /**
     * @var TestCase
     */
    protected $testCase;

    /**
     * @param TestCase $testCase
     *
     * @throws Exception
     */
    public function __construct(/* TestCase|\PHPUnit_Framework_TestCase */ $testCase)
    {
        if (!$testCase instanceof AdapterProviderTestInterface) {
            $class = get_class($testCase);
            throw new Exception("AdapterTestProvider can't be used with a class ($class) that doesn't implement AdapterProviderTestInterface.");
        }

        $this->testCase = $testCase;
    }

    /**
     * @return TestSuite
     */
    public function getSuite()
    {
        $suite = new TestSuite('Test integration');

        $i = 0;
        foreach ($this->getAdapterProviders() as $name => $adapterProvider) {
            $class = new \ReflectionClass(get_class($this->testCase));
            $tests = new TestSuite($class);

            // we can't use --filter to narrow down on specific adapters
            // (because we're not using dataProvider), but we can make sure it's
            // properly split up into groups & then use --group
            static::injectGroup($tests, $name);

            // and let's make sure to inject the specific adapter into the test
            static::injectAdapter($tests, $adapterProvider);

            // let's add all of the integrations tests for every adapter
            $suite->addTest($tests);

            ++$i;
        }

        return $suite;
    }

    /**
     * Injecting an adapter must be done recursively: there are some methods
     * that get input from dataProviders, so they're wrapped in another class
     * that we must unwrap in order to assign the adapter.
     *
     * @param TestSuite $suite
     */
    protected function injectAdapter(/* TestSuite|\PHPUnit_Framework_TestSuite */ $suite, AdapterProvider $adapterProvider)
    {
        foreach ($suite as $test) {
            /*
             * Testing for both current (namespace) and old (underscored)
             * PHPUnit class names, because (even though we stub this class)
             * $test may be a child of TestSuite/PHPUnit_Framework_TestSuite,
             * which the stub can't account for.
             * The PHPUnit_Framework_TestSuite part can be removed when support
             * for PHPUnit<6.0 is removed
             */
            if ($test instanceof TestSuite || $test instanceof \PHPUnit_Framework_TestSuite) {
                $this->injectAdapter($test, $adapterProvider);
            } else {
                /* @var AdapterTestCase $test */
                $test->setAdapter($adapterProvider->getAdapter());
                $test->setCollectionName($adapterProvider->getCollectionName());
            }
        }
    }

    /**
     * Because some tests are wrapped inside a dataProvider suite, we need to
     * make sure that the groups are recursively assigned to each suite until we
     * reach the child.
     *
     * @param TestSuite $suite
     * @param string$group
     */
    protected function injectGroup(/* TestSuite|\PHPUnit_Framework_TestSuite */ $suite, $group)
    {
        $tests = $suite->tests();
        $suite->setGroupDetails(array('default' => $tests, $group => $tests));

        foreach ($suite->tests() as $test) {
            /*
             * Testing for both current (namespace) and old (underscored)
             * PHPUnit class names, because (even though we stub this class)
             * $test may be a child of TestSuite/PHPUnit_Framework_TestSuite,
             * which the stub can't account for.
             * The PHPUnit_Framework_TestSuite part can be removed when support
             * for PHPUnit<6.0 is removed
             */
            if ($test instanceof TestSuite || $test instanceof \PHPUnit_Framework_TestSuite) {
                $this->injectGroup($test, $group);
            }
        }
    }

    /**
     * @return AdapterProvider[]
     */
    public function getAdapterProviders()
    {
        // re-use adapters across tests - if we keep initializing clients, they
        // may fail because of too much connections (and it's just overhead...)
        if (static::$adapters) {
            return static::$adapters;
        }

        $adapters = $this->getAllAdapterProviders();
        foreach ($adapters as $class) {
            try {
                /* @var AdapterProvider $adapter */
                $fqcn = "\\MatthiasMullie\\Scrapbook\\Tests\\Providers\\{$class}Provider";
                $adapter = new $fqcn();

                static::$adapters[$class] = $adapter;
            } catch (\Exception $e) {
                static::$adapters[$class] = new AdapterProvider(new AdapterStub($e));
            }
        }

        return static::$adapters;
    }

    /**
     * @return string[]
     */
    protected function getAllAdapterProviders()
    {
        $files = glob(__DIR__.'/Providers/*Provider.php');

        // since we're PSR-4, just stripping .php from the filename = classnames
        // also strip "Provider" suffix, which will again be appended later
        $adapters = array_map(function ($file) {
            return basename($file, 'Provider.php');
        }, $files);

        return $adapters;
    }
}
