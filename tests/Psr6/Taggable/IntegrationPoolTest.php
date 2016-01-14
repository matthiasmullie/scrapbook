<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6\Integration;

use Cache\IntegrationTests\CachePoolTest;
use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Psr6\Pool as OriginalPool;
use MatthiasMullie\Scrapbook\Psr6\Taggable\Pool as TaggablePool;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;
use MatthiasMullie\Scrapbook\Tests\AdapterProviderTestInterface;

class IntegrationPoolTest extends CachePoolTest implements AdapterProviderTestInterface
{
    /**
     * @var TaggablePool
     */
    protected $pool;

    /**
     * {@inheritdoc}
     */
    public static function suite()
    {
        $provider = new AdapterProvider(new static());

        return $provider->getSuite();
    }

    /**
     * {@inheritdoc}
     */
    public function setAdapter(KeyValueStore $adapter)
    {
        // create a PSR-6 cache
        $pool = new OriginalPool($adapter);

        // wrap PSR-6 cache into Taggable cache
        $this->pool = new TaggablePool($pool);
    }

    /**
     * @return TaggablePool
     */
    public function createCachePool()
    {
        return $this->pool;
    }
}
