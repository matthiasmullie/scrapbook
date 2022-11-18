<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters;

use League\Flysystem\FileExistsException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use MatthiasMullie\Scrapbook\Adapters\Collections\Flysystem as Collection;
use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * Flysystem adapter. Data will be written to League\Flysystem\Filesystem.
 *
 * Flysystem doesn't allow locking files, though. To guarantee interference from
 * other processes, we'll create separate lock-files to flag a cache key in use.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class Flysystem implements KeyValueStore
{
    protected Filesystem $filesystem;

    protected int $version;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        if (!class_exists(LocalFilesystemAdapter::class)) {
            $this->version = 1;
        } elseif (!method_exists($filesystem, 'has')) {
            $this->version = 2;
        } else {
            $this->version = 3;
        }
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        $token = null;

        // let expired-but-not-yet-deleted files be deleted first
        if (!$this->exists($key)) {
            return false;
        }

        $data = $this->read($key);
        if ($data === false) {
            return false;
        }

        $value = unserialize($data[1], ['allowed_classes' => true]);
        $token = $data[1];

        return $value;
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        $results = [];
        $tokens = [];
        foreach ($keys as $key) {
            $token = null;
            $value = $this->get($key, $token);

            if ($token !== null) {
                $results[$key] = $value;
                $tokens[$key] = $token;
            }
        }

        return $results;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        // we don't really need a lock for this operation, but we need to make
        // sure it's not locked by another operation, which we could overwrite
        if (!$this->lock($key)) {
            return false;
        }

        $expire = $this->normalizeTime($expire);
        if ($expire !== 0 && $expire < time()) {
            $this->unlock($key);

            // don't waste time storing (and later comparing expiration
            // timestamp) data that is already expired; just delete it already
            return !$this->exists($key) || $this->delete($key);
        }

        $path = $this->path($key);
        $data = $this->wrap($value, $expire);
        try {
            if ($this->version === 1) {
                $this->filesystem->put($path, $data);
            } else {
                $this->filesystem->write($path, $data);
            }
        } catch (FileExistsException $e) {
            // v1.x
            $this->unlock($key);

            return false;
        } catch (UnableToWriteFile $e) {
            // v2.x/3.x
            $this->unlock($key);

            return false;
        }

        return $this->unlock($key);
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        $success = [];
        foreach ($items as $key => $value) {
            // PHP treats numeric keys as integers, but they're allowed
            $key = (string) $key;
            $success[$key] = $this->set($key, $value, $expire);
        }

        return $success;
    }

    public function delete(string $key): bool
    {
        if (!$this->exists($key)) {
            return false;
        }

        if (!$this->lock($key)) {
            return false;
        }

        $path = $this->path($key);

        try {
            $this->filesystem->delete($path);

            return $this->unlock($key);
        } catch (FileNotFoundException $e) {
            // v1.x
            $this->unlock($key);

            return false;
        } catch (UnableToDeleteFile $e) {
            // v2.x/3.x
            $this->unlock($key);

            return false;
        }
    }

    public function deleteMulti(array $keys): array
    {
        $success = [];
        foreach ($keys as $key) {
            $success[$key] = $this->delete($key);
        }

        return $success;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        if (!$this->lock($key)) {
            return false;
        }

        if ($this->exists($key)) {
            $this->unlock($key);

            return false;
        }

        $path = $this->path($key);
        $data = $this->wrap($value, $expire);

        try {
            $this->filesystem->write($path, $data);

            return $this->unlock($key);
        } catch (FileExistsException $e) {
            // v1.x
            $this->unlock($key);

            return false;
        } catch (UnableToWriteFile $e) {
            // v2.x/3.x
            $this->unlock($key);

            return false;
        }
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        if (!$this->lock($key)) {
            return false;
        }

        if (!$this->exists($key)) {
            $this->unlock($key);

            return false;
        }

        $path = $this->path($key);
        $data = $this->wrap($value, $expire);

        try {
            if ($this->version === 1) {
                $this->filesystem->update($path, $data);
            } else {
                $this->filesystem->write($path, $data);
            }

            return $this->unlock($key);
        } catch (FileNotFoundException $e) {
            // v1.x
            $this->unlock($key);

            return false;
        } catch (UnableToWriteFile $e) {
            // v2.x/3.x
            $this->unlock($key);

            return false;
        }
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        if (!$this->lock($key)) {
            return false;
        }

        $current = $this->get($key);
        if ($token !== serialize($current)) {
            $this->unlock($key);

            return false;
        }

        $path = $this->path($key);
        $data = $this->wrap($value, $expire);

        try {
            if ($this->version === 1) {
                $this->filesystem->update($path, $data);
            } else {
                $this->filesystem->write($path, $data);
            }

            return $this->unlock($key);
        } catch (FileNotFoundException $e) {
            // v1.x
            $this->unlock($key);

            return false;
        } catch (UnableToWriteFile $e) {
            // v2.x/3.x
            $this->unlock($key);

            return false;
        }
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    public function touch(string $key, int $expire): bool
    {
        if (!$this->lock($key)) {
            return false;
        }

        $value = $this->get($key);
        if ($value === false) {
            $this->unlock($key);

            return false;
        }

        $path = $this->path($key);
        $data = $this->wrap($value, $expire);

        try {
            if ($this->version === 1) {
                $this->filesystem->update($path, $data);
            } else {
                $this->filesystem->write($path, $data);
            }

            return $this->unlock($key);
        } catch (FileNotFoundException $e) {
            // v1.x
            $this->unlock($key);

            return false;
        } catch (UnableToWriteFile $e) {
            // v2.x/3.x
            $this->unlock($key);

            return false;
        }
    }

    public function flush(): bool
    {
        $files = $this->filesystem->listContents('.');
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

    public function getCollection(string $name): KeyValueStore
    {
        /*
         * A better solution could be to simply construct a new object for a
         * subfolder, but we can't reliably create a new
         * `League\Flysystem\Filesystem` object for a subfolder from the
         * `$this->filesystem` object we have. I could `->getAdapter` and fetch
         * the path from there, but only if we can assume that the adapter is
         * `League\Flysystem\Adapter\Local` (1.x) or
         * `League\Flysystem\Local\LocalFilesystemAdapter` (2.x), which it may not be.
         * But I can just create a new object that changes the path to write at,
         * by prefixing it with a subfolder!
         */
        if ($this->version === 1) {
            $this->filesystem->createDir($name);
        } else {
            $this->filesystem->createDirectory($name);
        }

        return new Collection($this->filesystem, $name);
    }

    /**
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * split up (increment won't accept negative values).
     */
    protected function doIncrement(string $key, int $offset, int $initial, int $expire): int|false
    {
        $current = $this->get($key, $token);
        if ($current === false) {
            $success = $this->add($key, $initial, $expire);

            return $success ? $initial : false;
        }

        // NaN, doesn't compute
        if (!is_numeric($current)) {
            return false;
        }

        $value = $current + $offset;
        $value = max(0, $value);

        $success = $this->cas($token, $key, $value, $expire);

        return $success ? $value : false;
    }

    protected function exists(string $key): bool
    {
        $data = $this->read($key);
        if ($data === false) {
            return false;
        }

        $expire = $data[0];
        if ($expire !== 0 && $expire < time()) {
            // expired, don't keep it around
            $path = $this->path($key);
            $this->filesystem->delete($path);

            return false;
        }

        return true;
    }

    /**
     * Obtain a lock for a given key.
     * It'll try to get a lock for a couple of times, but ultimately give up if
     * no lock can be obtained in a reasonable time.
     */
    protected function lock(string $key): bool
    {
        $path = md5($key) . '.lock';

        for ($i = 0; $i < 25; ++$i) {
            try {
                $this->filesystem->write($path, '');

                return true;
            } catch (FileExistsException $e) {
                // v1.x
                usleep(200);
            } catch (UnableToWriteFile $e) {
                // v2.x/3.x
                usleep(200);
            }
        }

        return false;
    }

    /**
     * Release the lock for a given key.
     */
    protected function unlock(string $key): bool
    {
        $path = md5($key) . '.lock';
        try {
            $this->filesystem->delete($path);
        } catch (FileNotFoundException $e) {
            // v1.x
            return false;
        } catch (UnableToDeleteFile $e) {
            // v2.x/3.x
            return false;
        }

        return true;
    }

    /**
     * Times can be:
     * * relative (in seconds) to current time, within 30 days
     * * absolute unix timestamp
     * * 0, for infinity.
     *
     * The first case (relative time) will be normalized into a fixed absolute
     * timestamp.
     */
    protected function normalizeTime(int $time): int
    {
        // 0 = infinity
        if (!$time) {
            return 0;
        }

        // relative time in seconds, <30 days
        if ($time < 30 * 24 * 60 * 60) {
            $time += time();
        }

        return $time;
    }

    /**
     * Build value, token & expiration time to be stored in cache file.
     */
    protected function wrap(mixed $value, int $expire): string
    {
        $expire = $this->normalizeTime($expire);

        return $expire . "\n" . serialize($value);
    }

    /**
     * Fetch stored data from cache file.
     */
    protected function read(string $key): array|false
    {
        $path = $this->path($key);
        try {
            $data = $this->filesystem->read($path);
        } catch (FileNotFoundException $e) {
            // v1.x
            // unlikely given previous 'exists' check, but let's play safe...
            // (outside process may have removed it since)
            return false;
        } catch (UnableToReadFile $e) {
            // v2.x/3.x
            // unlikely given previous 'exists' check, but let's play safe...
            // (outside process may have removed it since)
            return false;
        }

        if ($data === false) {
            // in theory, a file could still be deleted between Flysystem's
            // assertPresent & the time it actually fetched the content;
            // extremely unlikely though
            return false;
        }

        $data = explode("\n", $data, 2);
        $data[0] = (int) $data[0];

        return $data;
    }

    protected function path(string $key): string
    {
        return md5($key) . '.cache';
    }
}
