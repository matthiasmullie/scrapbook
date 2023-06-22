<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MySQL;

use MatthiasMullie\Scrapbook\Tests\Buffered\AbstractBufferedStoreTestCase;

/**
 * @group buffered
 */
class BufferedStoreTest extends AbstractBufferedStoreTestCase
{
    use AdapterProviderTrait;
}
