<?php

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit\Framework\TestCase;

class AdapterTestCase extends TestCase implements AdapterProviderTestInterface
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
