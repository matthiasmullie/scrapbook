<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Adapters\Collections\Utils;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * @author Matthias Mullie <scrapbook@mullie.eu>
 * @copyright Copyright (c) 2014, Matthias Mullie. All rights reserved
 * @license LICENSE MIT
 */
class PrefixKeys implements KeyValueStore
{
    protected KeyValueStore $cache;

    protected string $prefix;

    public function __construct(KeyValueStore $cache, string $prefix)
    {
        $this->cache = $cache;
        $this->setPrefix($prefix);
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        $key = $this->prefix($key);

        return $this->cache->get($key, $token);
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        $keys = array_map([$this, 'prefix'], $keys);
        $results = $this->cache->getMulti($keys, $tokens);
        $keys = array_map([$this, 'unfix'], array_keys($results));
        $tokens = array_combine($keys, $tokens);

        return array_combine($keys, $results);
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        $key = $this->prefix($key);

        return $this->cache->set($key, $value, $expire);
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        $keys = array_map([$this, 'prefix'], array_keys($items));
        $items = array_combine($keys, $items);
        $results = $this->cache->setMulti($items, $expire);
        $keys = array_map([$this, 'unfix'], array_keys($results));

        return array_combine($keys, $results);
    }

    public function delete(string $key): bool
    {
        $key = $this->prefix($key);

        return $this->cache->delete($key);
    }

    public function deleteMulti(array $keys): array
    {
        $keys = array_map([$this, 'prefix'], $keys);
        $results = $this->cache->deleteMulti($keys);
        $keys = array_map([$this, 'unfix'], array_keys($results));

        return array_combine($keys, $results);
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        $key = $this->prefix($key);

        return $this->cache->add($key, $value, $expire);
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        $key = $this->prefix($key);

        return $this->cache->replace($key, $value, $expire);
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        $key = $this->prefix($key);

        return $this->cache->cas($token, $key, $value, $expire);
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        $key = $this->prefix($key);

        return $this->cache->increment($key, $offset, $initial, $expire);
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        $key = $this->prefix($key);

        return $this->cache->decrement($key, $offset, $initial, $expire);
    }

    public function touch(string $key, int $expire): bool
    {
        $key = $this->prefix($key);

        return $this->cache->touch($key, $expire);
    }

    public function flush(): bool
    {
        return $this->cache->flush();
    }

    public function getCollection(string $name): KeyValueStore
    {
        return $this->cache->getCollection($name);
    }

    protected function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    protected function prefix(string $key): string
    {
        return $this->prefix . $key;
    }

    protected function unfix(string $key): string
    {
        return preg_replace('/^' . preg_quote($this->prefix, '/') . '/', '', $key);
    }
}
