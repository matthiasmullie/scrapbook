<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\SQLite;

use MatthiasMullie\Scrapbook\Tests\Buffered\AbstractTransactionalStoreTestCase;

/**
 * @group transactional
 */
class TransactionalStoreTest extends AbstractTransactionalStoreTestCase
{
    use AdapterProviderTrait;
}
