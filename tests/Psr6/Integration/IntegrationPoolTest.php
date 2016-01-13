<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6\Integration;

use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class IntegrationPoolTest extends AdapterProviderTestCase
{
    public function adapterProvider()
    {
        return array_map(function (KeyValueStore $adapter) {
            $pool = new Pool($adapter);

            return array($adapter, $pool);
        }, $this->getAdapters());
    }

    /**
     * @dataProvider adapterProvider
     */
    public function testIntegration(KeyValueStore $cache, Pool $pool)
    {
        /*
         * So I want to run all IntegrationPoolTestIndividual tests for all
         * possible adapters (similar to all other tests), but it's an external
         * test suite that I can not just alter & add the dataProvider to.
         * Instead, I'll figure out what all tests in that suite are & manually
         * run them, injecting the data & dataName data in there.
         */
        $dataProperty = new \ReflectionProperty('PHPUnit_Framework_TestCase', 'data');
        $dataProperty->setAccessible(true);
        $dataNameProperty = new \ReflectionProperty('PHPUnit_Framework_TestCase', 'dataName');
        $dataNameProperty->setAccessible(true);

        $adapters = $this->adapterProvider();

        // these are the actual data & dataName that will go into the tests:
        // the adapter & pool and the name of the adapter
        $data = func_get_args();
        $dataName = array_search(func_get_args(), $adapters);

        // this TestSuite instance will let me iterate all the tests in it
        $suite = new \PHPUnit_Framework_TestSuite(
            new \ReflectionClass('MatthiasMullie\\Scrapbook\\Tests\\Psr6\\Integration\\IntegrationPoolTestIndividual')
        );

        /*
         * I need a way to inject the pool into the tests & there's little I can
         * do besides inject it into the result that will be passed along to the
         * tests. I can't pass it into the tests directly (like data & dataName)
         * because some of the tests are not instances of the test directly, but
         * TestSuite_DataProvider instances, which will in turn spawn the tests
         * with their dependencies.
         */
        $result = $this->getTestResultObject();
        $result->pool = $pool;

        /** @var IntegrationPoolTestIndividual $test */
        foreach ($suite as $test) {
            $dataProperty->setValue($test, $data);
            $dataNameProperty->setValue($test, $dataName);

            // run the test!
            $test->run($result);
        }

        // undo the hack of injecting the pool object
        unset($result->pool);
    }
}
