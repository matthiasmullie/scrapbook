<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;

class AdapterTestCase extends TestCase implements AdapterProviderTestInterface
{
    protected KeyValueStore $cache;

    protected string $collectionName;

    public static function suite(): TestSuite
    {
        return (new AdapterTestProvider(new static()))->getSuite();
    }

    public function setAdapter(KeyValueStore $adapter): void
    {
        $this->cache = $adapter;
    }

    public function setCollectionName(string $name): void
    {
        $this->collectionName = $name;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cache->flush();
    }
}
