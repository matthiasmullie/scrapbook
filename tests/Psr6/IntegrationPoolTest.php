<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use Cache\IntegrationTests\CachePoolTest;

/**
 * @group PSR6_Integration
 */
class IntegrationPoolTest extends CachePoolTest
{
    use IntegrationTestTrait;

    public function createCachePool()
    {
        return $this->getPool(getenv('ADAPTER'));
    }
}
