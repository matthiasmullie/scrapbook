<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use MatthiasMullie\Scrapbook\Exception\Exception;

class ApcTest implements AdapterInterface
{
    public function get()
    {
        if (!function_exists('apc_fetch')) {
            throw new Exception('ext-apc is not installed.');
        }

        return new \MatthiasMullie\Scrapbook\Adapters\Apc();
    }
}
