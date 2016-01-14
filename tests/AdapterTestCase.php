<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit_Framework_TestCase;

class AdapterTestCase extends PHPUnit_Framework_TestCase implements AdapterProviderTestInterface
{
    /**
     * @var KeyValueStore
     */
    protected $cache;

    public static function suite()
    {
        $provider = new AdapterProvider(new static());

        return $provider->getSuite();
    }

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
    }

    public function tearDown()
    {
        $this->cache->flush();
    }
}
