<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Couchbase;

use MatthiasMullie\Scrapbook\Tests\Scale\AbstractShardTestCase;

/**
 * @group shard
 */
class ShardTest extends AbstractShardTestCase
{
    use AdapterProviderTrait;
}
