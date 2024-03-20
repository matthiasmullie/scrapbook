<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Redis;

use MatthiasMullie\Scrapbook\Tests\Psr6\AbstractRepositoryTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('psr6')]
class Psr6RepositoryTest extends AbstractRepositoryTestCase
{
    use AdapterProviderTrait;
}
