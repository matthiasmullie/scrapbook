<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MySQL;

use MatthiasMullie\Scrapbook\Tests\Scale\AbstractShardTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('shard')]
class ShardTest extends AbstractShardTestCase
{
    use AdapterProviderTrait;
}
