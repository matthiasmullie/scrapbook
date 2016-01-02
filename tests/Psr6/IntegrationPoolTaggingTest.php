<?php

namespace MatthiasMullie\Scrapbook\Tests\Psr6;

use Cache\IntegrationTests\TaggableCachePoolTest;

/**
 * @group PSR6_Integration
 */
class IntegrationPoolTaggingTest extends TaggableCachePoolTest
{
    use IntegrationTestTrait;

    public function createCachePool()
    {
        return $this->getPool(getenv('ADAPTER'));
    }
}
