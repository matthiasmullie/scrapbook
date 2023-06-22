<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\PostgreSQL;

use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;

/**
 * @group keyvaluestore
 */
class KeyValueStoreTest extends AbstractKeyValueStoreTestCase
{
    use AdapterProviderTrait;
}
