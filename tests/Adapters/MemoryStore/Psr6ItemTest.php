<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\MemoryStore;

use MatthiasMullie\Scrapbook\Tests\Psr6\AbstractItemTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('psr6')]
class Psr6ItemTest extends AbstractItemTestCase
{
    use AdapterProviderTrait;
}
