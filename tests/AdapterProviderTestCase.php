<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;
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

        $env = getenv('ADAPTER');
        $adapters = $env ? array($env) : $this->getAllAdapters();

        $failures = array();
        foreach ($adapters as $class) {
            try {
                static::$adapters[] = $this->getAdapter($class);
            } catch (Exception $e) {
                // ignore failures during setup (e.g. Couchbase may
                // have an unhealthy server from time to time)
                $failures[] = $class;
            }
        }

        // unless the environment was specified, let's just ignore those that
        // fail to init, so we don't need to setup every single adapter
        if (!static::$adapters && !$env) {
            $this->markTestSkipped('Failed to initialize '.implode($failures));
        }

        return static::$adapters;
    }

    /**
     * @param string $class
     *
     * @return KeyValueStore
     *
     * @throws \Exception Any exception could be thrown, depending on client
     */
    protected function getAdapter($class)
    {
        $fqcn = "\\MatthiasMullie\\Scrapbook\\Tests\\Adapters\\{$class}Test";

        /** @var AdapterInterface $adapter */
        $adapter = new $fqcn();

        return $adapter->get();
    }

    /**
     * @return string[]
     */
    protected function getAllAdapters()
    {
        $files = scandir(__DIR__.'/Adapters');

        // get rid of '.', '..' & AdapterInterface
        unset($files[0], $files[1], $files[array_search('AdapterInterface.php', $files)]);
        $files = array_values($files);

        // since we're PSR-4, just stripping .php from the filename = classnames
        // also strip "Test" suffix, which will again be appended later
        $adapters = array_map(function ($file) {
            return preg_replace('/\Test.php$/', '', $file);
        }, $files);

        return $adapters;
    }

    protected function setUp()
    {
        parent::setUp();

        foreach ($this->getAdapters() as $cache) {
            $cache->flush();
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
