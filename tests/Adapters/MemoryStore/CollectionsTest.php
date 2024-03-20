<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MemoryStore;

use MatthiasMullie\Scrapbook\Tests\Collections\AbstractCollectionsTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('keyvaluestore')]
#[Group('collections')]
class CollectionsTest extends AbstractCollectionsTestCase
{
    use AdapterProviderTrait;
}
