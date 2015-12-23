<?php

namespace MatthiasMullie\Scrapbook\Adapters;

use MatthiasMullie\Scrapbook\KeyValueStore;
use MatthiasMullie\Scrapbook\Exception\Exception;

/**
 * Filesystem adapter. Data will be written to filesystem, in separate files.
 *
 * @deprecated Use Adapters\Flysystem instead.
 *
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved.
 * @license MIT License
 */
class Filesystem implements KeyValueStore
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $path
     *
     * @throws Exception
     */
    public function __construct($path)
    {
        if (!is_writable($path)) {
            throw new Exception("$path is not writable.");
        }

        $this->path = rtrim($path, '/');
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, &$token = null)
    {
        $path = $this->path($key);
        if (!file_exists($path)) {
            return false;
        }

        $data = file_get_contents($path);
        $data = explode("\n", $data, 2);

        $expiration = (int) $data[0];
        if ($expiration !== 0 && $expiration < time()) {
            // expired
            return false;
        }

        $token = $data[1];

        return unserialize($data[1]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMulti(array $keys, array &$tokens = null)
    {
        $results = array();
        $tokens = array();
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

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $expire = 0)
    {
        $expire = $this->normalizeTime($expire);
        if ($expire !== 0 && $expire < time()) {
            // don't waste time storing (and later comparing expiration
            // timestamp) data that is already expired; just delete it already
            $this->delete($key);

            return true;
        }

        $data = $expire."\n".serialize($value);
        $success = file_put_contents($this->path($key), $data, \LOCK_EX);

        return $success !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function setMulti(array $items, $expire = 0)
    {
        $success = array();
        foreach ($items as $key => $value) {
            $success[$key] = $this->set($key, $value, $expire);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return @unlink($this->path($key));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMulti(array $keys)
    {
        $success = array();
        foreach ($keys as $key) {
            $success[$key] = $this->delete($key);
        }

        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function add($key, $value, $expire = 0)
    {
        $expire = $this->normalizeTime($expire);

        // I would like to just fopen with x flag (checks file existence), but
        // it's also perfectly possible that a file exists but has already been
        // expired, so I'll use get() to check if it's ok to add...

        $handle = @fopen($this->path($key), 'r+');
        if ($handle === false) {
            // file doesn't yet exist, set it!
            return $this->set($key, $value, $expire);
        }

        flock($handle, LOCK_EX);
        $expiration = (int) fgets($handle);

        if ($expiration === 0 || $expiration >= time()) {
            // not yet expired, can't add
            fclose($handle);

            return false;
        }

        // file exists, but is expired, we can add!
        $data = $expire."\n".serialize($value);
        ftruncate($handle, 0);
        rewind($handle);
        $success = fwrite($handle, $data);
        fclose($handle);

        return $success !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function replace($key, $value, $expire = 0)
    {
        $handle = @fopen($this->path($key), 'r+');
        if ($handle === false) {
            // file doesn't yet exist
            return false;
        }

        flock($handle, LOCK_EX);
        $expiration = (int) fgets($handle);

        if ($expiration !== 0 && $expiration < time()) {
            // already expired, can't do replace
            fclose($handle);

            return false;
        }

        $expire = $this->normalizeTime($expire);
        $data = $expire."\n".serialize($value);
        ftruncate($handle, 0);
        rewind($handle);
        $success = fwrite($handle, $data);
        fclose($handle);

        return $success !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function cas($token, $key, $value, $expire = 0)
    {
        $handle = @fopen($this->path($key), 'r+');
        if ($handle === false) {
            // file doesn't yet exist
            return false;
        }

        flock($handle, LOCK_EX);
        $expiration = (int) fgets($handle);

        if ($expiration !== 0 && $expiration < time()) {
            // already expired, can't do CAS
            fclose($handle);

            return false;
        }

        $compare = fgets($handle);
        if ($token !== $compare) {
            // token doesn't match what's currently in cache
            fclose($handle);

            return false;
        }

        $expire = $this->normalizeTime($expire);
        $data = $expire."\n".serialize($value);
        ftruncate($handle, 0);
        rewind($handle);
        $success = fwrite($handle, $data);
        fclose($handle);

        return $success !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function increment($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, $offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function decrement($key, $offset = 1, $initial = 0, $expire = 0)
    {
        if ($offset <= 0 || $initial < 0) {
            return false;
        }

        return $this->doIncrement($key, -$offset, $initial, $expire);
    }

    /**
     * {@inheritdoc}
     */
    public function touch($key, $expire)
    {
        $handle = @fopen($this->path($key), 'r+');
        if ($handle === false) {
            // file doesn't yet exist
            return false;
        }

        flock($handle, LOCK_EX);
        $expiration = (int) fgets($handle);

        if ($expiration !== 0 && $expiration < time()) {
            // already expired, can't touch
            fclose($handle);

            return false;
        }

        $expire = $this->normalizeTime($expire);
        $data = $expire."\n".fgets($handle);
        ftruncate($handle, 0);
        rewind($handle);
        $success = fwrite($handle, $data);
        fclose($handle);

        return $success !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $files = glob($this->path.'/*.cache');
        $success = true;
        foreach ($files as $file) {
            $success &= @unlink($file);
        }

        // always true; don't care if we failed to unlink something, might have
        // been deleted by other process in the meantime...
        return true;
    }

    /**
     * Shared between increment/decrement: both have mostly the same logic
     * (decrement just increments a negative value), but need their validation
     * split up (increment won't accept negative values).
     *
     * @param string $key
     * @param int    $offset
     * @param int    $initial
     * @param int    $expire
     *
     * @return int|bool
     */
    protected function doIncrement($key, $offset, $initial, $expire)
    {
        $handle = @fopen($this->path($key), 'r+');
        if ($handle === false) {
            // file doesn't yet exist, set initial value
            $success = $this->set($key, $initial, $expire);

            return $success ? $initial : false;
        }

        flock($handle, LOCK_EX);
        $expiration = (int) fgets($handle);

        if ($expiration !== 0 && $expiration < time()) {
            // already expired, set initial value
            $expire = $this->normalizeTime($expire);
            $data = $expire."\n".serialize($initial);
            ftruncate($handle, 0);
            rewind($handle);
            $success = fwrite($handle, $data);
            fclose($handle);

            return $success ? $initial : false;
        }

        $value = fgets($handle);
        $value = unserialize($value);

        if (!is_numeric($value)) {
            // NaN, doesn't compute
            fclose($handle);

            return false;
        }

        $value += $offset;
        $value = max(0, $value);

        $expire = $this->normalizeTime($expire);
        $data = $expire."\n".serialize($value);
        ftruncate($handle, 0);
        rewind($handle);
        $success = fwrite($handle, $data);
        fclose($handle);

        return $success ? $value : false;
    }

    /**
     * Times can be:
     * * relative (in seconds) to current time, within 30 days
     * * absolute unix timestamp
     * * 0, for infinity.
     *
     * The first case (relative time) will be normalized into a fixed absolute
     * timestamp.
     *
     * @param int $time
     *
     * @return int
     */
    protected function normalizeTime($time)
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
     * Returns the path to the cache file for the given key.
     *
     * @param string $key
     *
     * @return string
     */
    protected function path($key)
    {
        return $this->path.'/'.urlencode($key).'.cache';
    }
}
