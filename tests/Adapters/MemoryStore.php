<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

class MemoryStore implements AdapterInterface
{
    public function get()
    {
        return new \MatthiasMullie\Scrapbook\Adapters\MemoryStore();
    }
}
