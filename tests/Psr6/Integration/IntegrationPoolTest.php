<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6\Integration;

use Cache\IntegrationTests\CachePoolTest;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool;
use MatthiasMullie\Scrapbook\Tests\AdapterTestProvider;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestInterface;

class IntegrationPoolTest extends CachePoolTest implements AdapterProviderTestInterface
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
     * @return Pool
     */
    public function createCachePool()
    {
        return new Pool($this->adapter);
    }
}
