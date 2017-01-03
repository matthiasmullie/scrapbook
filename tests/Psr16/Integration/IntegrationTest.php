<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16\Integration;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AdapterTestProvider;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestInterface;

class IntegrationTest extends SimpleCacheTestCase implements AdapterProviderTestInterface
{
    /**
     * @var KeyValueStore
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $collectionName;

    /**
     * {@inheritdoc}
     */
    public static function suite()
    {
        $provider = new AdapterTestProvider(new static());

        return $provider->getSuite();
    }

    /**
     * {@inheritdoc}
     */
    public function setAdapter(KeyValueStore $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function setCollectionName($name)
    {
        $this->collectionName = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function createSimpleCache()
    {
        return new SimpleCache($this->adapter);
    }
}
