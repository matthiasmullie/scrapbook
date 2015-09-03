<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class Apc implements AdapterInterface
{
    public function get()
    {
        return new \MatthiasMullie\Scrapbook\Adapters\Apc();
    }
}
