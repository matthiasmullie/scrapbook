<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6\Integration;

use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestCase;
use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit_Framework_TestSuite;

class IntegrationPoolTest extends AdapterProviderTestCase
{
    public static function suite()
    {
        $suite = new PHPUnit_Framework_TestSuite('Test integration');

        $self = new static;
        $i = 0;
        foreach ($self->getAdapters() as $name => $adapter) {
            // let's add all of the integrations tests for every adapter
            $suite->addTestSuite('MatthiasMullie\\Scrapbook\\Tests\\Psr6\\Integration\\IntegrationPoolTestIndividual');

            // we can't use --filter to narrow down on specific adapters
            // (because we're not using dataProvider), but we can make sure it's
            // properly split up into groups & then use --group
            $tests = $suite->testAt($i);
            static::injectGroup($tests, $name);

            // and let's make sure to inject the specific adapter into the test
            static::injectAdapter($tests, $adapter);

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
    protected static function injectAdapter(\PHPUnit_Framework_TestSuite $suite, KeyValueStore $adapter)
    {
        foreach ($suite as $test)
        {
            if ($test instanceof \PHPUnit_Framework_TestSuite) {
                static::injectAdapter($test, $adapter);
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
     * @param PHPUnit_Framework_TestSuite $suite
     * @param string$group
     */
    protected static function injectGroup(\PHPUnit_Framework_TestSuite $suite, $group)
    {
        $tests = $suite->tests();
        $suite->setGroupDetails(array('default' => $tests, $group => $tests));

        foreach ($suite->tests() as $test)
        {
            if ($test instanceof \PHPUnit_Framework_TestSuite) {
                static::injectGroup($test, $group);
            }
        }
    }
}
