<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Memcached;

use MatthiasMullie\Scrapbook\Tests\Buffered\AbstractTransactionalStoreTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('transactional')]
class TransactionalStoreTest extends AbstractTransactionalStoreTestCase
{
    use AdapterProviderTrait;
}
