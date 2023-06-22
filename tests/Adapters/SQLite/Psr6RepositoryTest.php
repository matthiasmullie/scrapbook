<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\SQLite;

use MatthiasMullie\Scrapbook\Tests\Psr6\AbstractRepositoryTestCase;

/**
 * @group psr6
 */
class Psr6RepositoryTest extends AbstractRepositoryTestCase
{
    use AdapterProviderTrait;
}
