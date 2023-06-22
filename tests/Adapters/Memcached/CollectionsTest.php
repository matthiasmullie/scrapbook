<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Memcached;

use MatthiasMullie\Scrapbook\Tests\Collections\AbstractCollectionsTestCase;

/**
 * @group keyvaluestore
 * @group collections
 */
class CollectionsTest extends AbstractCollectionsTestCase
{
    use AdapterProviderTrait;
}
