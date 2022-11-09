<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests\Providers;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MatthiasMullie\Scrapbook\Adapters\Flysystem;
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

        if (class_exists(LocalFilesystemAdapter::class)) {
            // flysystem v2.x/3,x
            $adapter = new LocalFilesystemAdapter($path, null, LOCK_EX);
        } elseif (class_exists(Local::class)) {
            // flysystem v1.x
            $adapter = new Local($path, LOCK_EX);
        } else {
            throw new Exception('Flysystem is not available.');
        }

        $filesystem = new Filesystem($adapter);

        parent::__construct(new Flysystem($filesystem));
    }
}
