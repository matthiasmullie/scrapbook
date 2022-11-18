<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\Exception\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;

class AdapterTestProvider
{
    /**
     * @var AdapterProvider[]
     */
    protected static array $adapters = [];

    protected TestCase $testCase;

    /**
     * @throws Exception
     */
    public function __construct(TestCase $testCase)
    {
        if (!$testCase instanceof AdapterProviderTestInterface) {
            $class = get_class($testCase);
            throw new Exception("AdapterTestProvider can't be used with a class ($class) that doesn't implement AdapterProviderTestInterface.");
        }

        $this->testCase = $testCase;
    }

    public function getSuite(): TestSuite
    {
        $suite = new TestSuite('Test integration');

        foreach ($this->getAdapterProviders() as $name => $adapterProvider) {
            $class = new \ReflectionClass(get_class($this->testCase));
            $tests = new TestSuite($class);

            // we can't use --filter to narrow down on specific adapters
            // (because we're not using dataProvider), but we can make sure it's
            // properly split up into groups & then use --group
            $this->injectGroup($tests, $name);

            // and let's make sure to inject the specific adapter into the test
            $this->injectAdapter($tests, $adapterProvider);

            // let's add all of the integrations tests for every adapter
            $suite->addTest($tests);
        }

        return $suite;
    }

    /**
     * Injecting an adapter must be done recursively: there are some methods
     * that get input from dataProviders, so they're wrapped in another class
     * that we must unwrap in order to assign the adapter.
     */
    protected function injectAdapter(TestSuite|AdapterTestCase $suite, AdapterProvider $adapterProvider): void
    {
        foreach ($suite as $test) {
            if ($test instanceof TestSuite) {
                $this->injectAdapter($test, $adapterProvider);
            } else {
                $test->setAdapter($adapterProvider->getAdapter());
                $test->setCollectionName($adapterProvider->getCollectionName());
            }
        }
    }

    /**
     * Because some tests are wrapped inside a dataProvider suite, we need to
     * make sure that the groups are recursively assigned to each suite until we
     * reach the child.
     */
    protected function injectGroup(TestSuite $suite, string $group): void
    {
        $tests = $suite->tests();
        $suite->setGroupDetails(['default' => $tests, $group => $tests]);

        foreach ($suite->tests() as $test) {
            if ($test instanceof TestSuite) {
                $this->injectGroup($test, $group);
            }
        }
    }

    /**
     * @return AdapterProvider[]
     */
    public function getAdapterProviders(): array
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
    protected function getAllAdapterProviders(): array
    {
        $files = glob(__DIR__ . '/Providers/*Provider.php');

        // since we're PSR-4, just stripping .php from the filename = classnames
        // also strip "Provider" suffix, which will again be appended later
        return array_map(static function ($file) {
            return basename($file, 'Provider.php');
        }, $files);
    }
}
