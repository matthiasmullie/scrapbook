<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr16\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr16\SimpleCache;
use MatthiasMullie\Scrapbook\Tests\AdapterTestProvider;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestInterface;

class IntegrationTest extends SimpleCacheTest implements AdapterProviderTestInterface
{
    /**
     * {@inheritdoc}
     */
    protected $skippedTests = [
        'testSetInvalidTtl' => 'Skipping test because this is not defined in PSR-16',
        'testSetMultipleInvalidTtl' => 'Skipping test because this is not defined in PSR-16',
        // below 2 tests are unreliable until
        // https://github.com/php-cache/integration-tests/pull/80 is merged
        'testSetTtl' => 'Skipping unreliable test',
        'testSetMultipleTtl' => 'Skipping unreliable test',
    ];

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
     * @return SimpleCache
     */
    public function createSimpleCache()
    {
        return new SimpleCache($this->adapter);
    }
}
