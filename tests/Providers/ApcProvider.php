<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use MatthiasMullie\Scrapbook\Adapters\Apc;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class ApcProvider extends AdapterProvider
{
    public function __construct()
    {
        if (!function_exists('apcu_fetch')) {
            throw new Exception('ext-apcu is not installed.');
        }

        parent::__construct(new Apc());
    }
}
