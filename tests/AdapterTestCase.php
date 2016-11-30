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

    /**
     * @var string
     */
    protected $collectionName;

    public static function suite()
    {
        $provider = new AdapterTestProvider(new static());

        return $provider->getSuite();
    }

    public function setAdapter(KeyValueStore $adapter)
    {
        $this->cache = $adapter;
    }

    public function setCollectionName($name)
    {
        $this->collectionName = $name;
    }

    public function tearDown()
    {
        $this->cache->flush();
    }
}
