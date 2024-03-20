<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Redis;

use MatthiasMullie\Scrapbook\Tests\AbstractKeyValueStoreTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('keyvaluestore')]
class KeyValueStoreTest extends AbstractKeyValueStoreTestCase
{
    use AdapterProviderTrait;
}
