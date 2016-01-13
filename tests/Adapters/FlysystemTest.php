<?php

namespace MatthiasMullie\Scrapbook\Tests\Adapters;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * @group default
 * @group Flysystem
 */
class FlysystemTest implements AdapterInterface
{
    public function get()
    {
        $path = '/tmp/cache';

        if (!is_writable($path)) {
            throw new Exception($path.' is not writable.');
        }

        if (!class_exists('League\Flysystem\Filesystem')) {
            throw new Exception('Flysystem is not available.');
        }

        $adapter = new Local($path, LOCK_EX);
        $filesystem = new FlysystemFilesystem($adapter);

        return new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem);
    }
}
