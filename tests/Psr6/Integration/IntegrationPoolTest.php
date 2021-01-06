<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6\Integration;

use Cache\IntegrationTests\CachePoolTest;
use MatthiasMullie\Scrapbook\Adapters\Collections\Couchbase as CouchbaseCollection;
use MatthiasMullie\Scrapbook\Adapters\Collections\Memcached as MemcachedCollection;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Adapters\Memcached;
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
    protected function setUp()
    {
        parent::setUp();

        if ($this->adapter instanceof Couchbase || $this->adapter instanceof CouchbaseCollection) {
            $this->skippedTests['testExpiration'] = "Couchbase TTL can't be relied on with 1 second precision";
            $this->skippedTests['testHasItemReturnsFalseWhenDeferredItemIsExpired'] = "Couchbase TTL can't be relied on with 1 second precision";
        } elseif ($this->adapter instanceof Memcached || $this->adapter instanceof MemcachedCollection) {
            $this->skippedTests['testBasicUsageWithLongKey'] = "Memcached keys can't exceed 255 characters";
        }
    }

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
