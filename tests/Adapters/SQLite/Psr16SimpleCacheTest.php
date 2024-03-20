<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\SQLite;

use MatthiasMullie\Scrapbook\Tests\Psr16\AbstractSimpleCacheTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('psr16')]
class Psr16SimpleCacheTest extends AbstractSimpleCacheTestCase
{
    use AdapterProviderTrait;
}
