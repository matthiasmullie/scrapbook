<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Memcached;

use MatthiasMullie\Scrapbook\Tests\Scale\AbstractStampedeProtectorTestCase;

/**
 * @group stampede
 */
class StampedeProtectorTest extends AbstractStampedeProtectorTestCase
{
    use AdapterProviderTrait;
}
