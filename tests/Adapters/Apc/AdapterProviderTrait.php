<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Adapters\Apc;

use MatthiasMullie\Scrapbook\Adapters\Apc;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\KeyValueStore;

trait AdapterProviderTrait
{
    public function getAdapterKeyValueStore(): KeyValueStore
    {
        if (!function_exists('apcu_fetch')) {
            throw new Exception('ext-apcu is not installed.');
        }

        return new Apc();
    }

    public function getCollectionName(): string
    {
        return 'collection';
    }
}
