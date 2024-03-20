<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\SQLite;

use MatthiasMullie\Scrapbook\Tests\Buffered\AbstractBufferedStoreTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('buffered')]
class BufferedStoreTest extends AbstractBufferedStoreTestCase
{
    use AdapterProviderTrait;
}
