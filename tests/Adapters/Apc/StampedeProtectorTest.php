<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Apc;

use MatthiasMullie\Scrapbook\Tests\Scale\AbstractStampedeProtectorTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('stampede')]
class StampedeProtectorTest extends AbstractStampedeProtectorTestCase
{
    use AdapterProviderTrait;
}
