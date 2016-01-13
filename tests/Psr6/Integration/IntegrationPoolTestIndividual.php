<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6\Integration;

use Cache\IntegrationTests\CachePoolTest;
use MatthiasMullie\Scrapbook\Psr6\Pool;

class IntegrationPoolTestIndividual extends CachePoolTest
{
    /**
     * @return Pool
     */
    public function createCachePool()
    {
        return $this->getTestResultObject()->pool;
    }
}
