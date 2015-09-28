<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class ApcTest implements AdapterInterface
{
    public function get()
    {
        return new \MatthiasMullie\Scrapbook\Adapters\Apc();
    }
}
