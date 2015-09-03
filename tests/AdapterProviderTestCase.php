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
    protected $adapters = array();

    /**
     * @return KeyValueStore[]
     */
    protected function getAdapters()
    {
        if ($this->adapters) {
            return $this->adapters;
        }

        $env = getenv('ADAPTER');
        $adapters = $env ? array($env) : $this->getAllAdapters();

        $failures = array();
        foreach ($adapters as $class) {
            $fqcn = "\\MatthiasMullie\\Scrapbook\\Tests\\Adapters\\$class";

            /** @var AdapterInterface $adapter */
            $adapter = new $fqcn();

            try {
                $this->adapters[] = $adapter->get();
            } catch (Exception $e) {
                // ignore failures during setup (e.g. Couchbase may
                // have an unhealthy server from time to time)
                $failures[] = $class;
            }
        }

        if (!$this->adapters) {
            $this->markTestSkipped('Failed to initialize '.implode($failures));
        }

        return $this->adapters;
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
        $adapters = array_map(function ($file) {
            return preg_replace('/\.php$/', '', $file);
        }, $files);

        return $adapters;
    }

    protected function tearDown()
    {
        parent::tearDown();

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
