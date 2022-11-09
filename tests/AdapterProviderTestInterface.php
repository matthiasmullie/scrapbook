<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;
use PHPUnit\Framework\TestSuite;

interface AdapterProviderTestInterface
{
    public static function suite(): TestSuite;

    /**
     * This is where AdapterProvider will inject the adapter to.
     */
    public function setAdapter(KeyValueStore $adapter): void;

    /**
     * This is where AdapterProvider will inject the adapter to.
     */
    public function setCollectionName(string $name): void;
}
