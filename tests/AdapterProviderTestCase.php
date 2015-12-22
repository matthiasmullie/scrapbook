<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Tests\Adapters\AdapterStub;
use MatthiasMullie\Scrapbook\Tests\Adapters\AdapterInterface;
use PHPUnit_Framework_TestCase;

abstract class AdapterProviderTestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var KeyValueStore[]
     */
    protected static $adapters = array();

    /**
     * @return KeyValueStore[]
     */
    protected function getAdapters()
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
                static::$adapters[$class] = new AdapterStub($this, $e);
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

    protected function setUp()
    {
        parent::setUp();

        foreach ($this->getAdapters() as $name => $cache) {
            // we support --filter attribute to only run only specific adapters,
            // so we don't need to waste time flushing those we're not testing
            $dataset = $this->getDataSetAsString();
            if (strpos($dataset, $name) !== false) {
                $cache->flush();
            }
        }
    }

    /**
     * @return KeyValueStore[][]
     */
    public function adapterProvider()
    {
        return array_map(function (KeyValueStore $adapter) {
            return array($adapter);
        }, $this->getAdapters());
    }
}
