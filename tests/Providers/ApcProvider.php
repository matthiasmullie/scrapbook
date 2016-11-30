<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Exception\Exception;

class ApcProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!function_exists('apc_fetch') && !function_exists('apcu_fetch')) {
            throw new Exception('ext-apc(u) is not installed.');
        }

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\Apc());
    }
}
