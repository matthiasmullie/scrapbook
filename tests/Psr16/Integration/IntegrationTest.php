<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use MatthiasMullie\Scrapbook\Adapters\Collections\Couchbase as CouchbaseCollection;
use MatthiasMullie\Scrapbook\Adapters\Collections\Memcached as MemcachedCollection;
use MatthiasMullie\Scrapbook\Adapters\Couchbase;
use MatthiasMullie\Scrapbook\Adapters\Memcached;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestInterface;
use MatthiasMullie\Scrapbook\Tests\AdapterTestProvider;

class IntegrationTest extends SimpleCacheTest implements AdapterProviderTestInterface
{
    /**
     * {@inheritdoc}
     */
    protected $skippedTests = array(
        'testSetInvalidTtl' => 'Skipping test because this is not defined in PSR-16',
        'testSetMultipleInvalidTtl' => 'Skipping test because this is not defined in PSR-16',
        // below 2 tests are unreliable until
        // https://github.com/php-cache/integration-tests/pull/80 is merged
        'testSetTtl' => 'Skipping unreliable test',
        'testSetMultipleTtl' => 'Skipping unreliable test',
    );

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
    protected function compatSetUp()
    {
        parent::compatSetUp();

        if ($this->adapter instanceof Couchbase || $this->adapter instanceof CouchbaseCollection) {
            $this->skippedTests['testSetTtl'] = "Couchbase TTL can't be relied on with 1 second precision";
            $this->skippedTests['testSetMultipleTtl'] = "Couchbase TTL can't be relied on with 1 second precision";
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
     * @return SimpleCache
     */
    public function createSimpleCache()
    {
        return new SimpleCache($this->adapter);
    }
}
