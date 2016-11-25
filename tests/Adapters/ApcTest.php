<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * @group default
 * @group MemoryStore
 */
class ApcTest implements AdapterInterface
{
    public function get()
    {
        if (!function_exists('apc_fetch') && !function_exists('apcu_fetch')) {
            throw new Exception('ext-apc(u) is not installed.');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Apc();
    }
}
