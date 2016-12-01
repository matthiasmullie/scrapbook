<?php

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use MatthiasMullie\Scrapbook\Exception\Exception;
use MatthiasMullie\Scrapbook\Tests\AdapterProvider;

class FlysystemProvider extends AdapterProvider
{
    public function __construct()
    {
        $path = '/tmp/cache';

        if (!is_writable($path)) {
            throw new Exception($path.' is not writable.');
        }

        if (!class_exists('League\Flysystem\Filesystem')) {
            throw new Exception('Flysystem is not available.');
        }

        $adapter = new Local($path, LOCK_EX);
        $filesystem = new Filesystem($adapter);

        parent::__construct(new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem));
    }
}
