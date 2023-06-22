<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MemoryStore;

use MatthiasMullie\Scrapbook\Tests\Psr6\AbstractPoolTestCase;

/**
 * @group psr6
 */
class Psr6PoolTest extends AbstractPoolTestCase
{
    use AdapterProviderTrait;
}
