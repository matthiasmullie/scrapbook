<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToDeleteFile;
use MatthiasMullie\Scrapbook\Adapters\Flysystem as Adapter;

/**
 * Flysystem adapter for a subset of data, in a subfolder.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Flysystem extends Adapter
{
    protected string $collection;

    public function __construct(Filesystem $filesystem, string $collection)
    {
        parent::__construct($filesystem);
        $this->collection = $collection;
    }

    public function flush(): bool
    {
        $files = $this->filesystem->listContents($this->collection);
        foreach ($files as $file) {
            try {
                if ($file['type'] === 'dir') {
                    if ($this->version === 1) {
                        $this->filesystem->deleteDir($file['path']);
                    } else {
                        $this->filesystem->deleteDirectory($file['path']);
                    }
                } else {
                    $this->filesystem->delete($file['path']);
                }
            } catch (FileNotFoundException $e) {
                // v1.x
                // don't care if we failed to unlink something, might have
                // been deleted by another process in the meantime...
            } catch (UnableToDeleteFile $e) {
                // v2.x/3.x
                // don't care if we failed to unlink something, might have
                // been deleted by another process in the meantime...
            }
        }

        return true;
    }

    protected function path(string $key): string
    {
        return $this->collection . '/' . parent::path($key);
    }
}
