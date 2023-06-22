<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit\Framework\TestCase;

abstract class AbstractAdapterTestCase extends TestCase
{
    protected KeyValueStore $adapterKeyValueStore;
    protected string $collectionName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collectionName = $this->getCollectionName();
        $this->adapterKeyValueStore = $this->getAdapterKeyValueStore();
    }

    abstract public function getAdapterKeyValueStore(): KeyValueStore;

    abstract public function getCollectionName(): string;
}
