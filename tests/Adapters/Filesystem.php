<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class Filesystem implements AdapterInterface
{
    public function get()
    {
        return new \MatthiasMullie\Scrapbook\Adapters\Filesystem('/tmp/cache');
    }
}
