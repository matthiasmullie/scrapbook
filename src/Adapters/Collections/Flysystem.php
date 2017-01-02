<?php

namespace MatthiasMullie\Scrapbook\Adapters\Collections;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
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
    /**
     * @var string
     */
    protected $collection;

    /**
     * @param Filesystem $filesystem
     * @param string     $collection
     */
    public function __construct(Filesystem $filesystem, $collection)
    {
        parent::__construct($filesystem);
        $this->collection = $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $files = $this->filesystem->listContents($this->collection);
        foreach ($files as $file) {
            try {
                if ($file['type'] === 'dir') {
                    $this->filesystem->deleteDir($file['path']);
                } else {
                    $this->filesystem->delete($file['path']);
                }
            } catch (FileNotFoundException $e) {
                // don't care if we failed to unlink something, might have
                // been deleted by another process in the meantime...
            }
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function path($key)
    {
        return $this->collection.'/'.parent::path($key.'.cache');
    }
}
