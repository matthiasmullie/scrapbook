<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MemoryStore;

use MatthiasMullie\Scrapbook\Tests\Psr16\AbstractSimpleCacheTestCase;

/**
 * @group psr16
 */
class Psr16SimpleCacheTest extends AbstractSimpleCacheTestCase
{
    use AdapterProviderTrait;
}
