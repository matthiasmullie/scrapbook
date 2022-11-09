<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;

class AdapterProvider
{
    protected KeyValueStore $adapter;

    protected string $collectionName;

    public function __construct(KeyValueStore $adapter, string $collectionName = 'collection')
    {
        $this->adapter = $adapter;
        $this->collectionName = $collectionName;
    }

    public function getAdapter(): KeyValueStore
    {
        return $this->adapter;
    }

    public function getCollectionName(): string
    {
        return $this->collectionName;
    }
}
