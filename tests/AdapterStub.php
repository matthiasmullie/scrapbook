<?php

declare(strict_types=1);

namespace MatthiasMullie\Scrapbook\Tests;

use MatthiasMullie\Scrapbook\KeyValueStore;

/**
 * If an adapter fails to initialize, we'll want to proceed with the tests
 * anyway, just add tests for this particular adapter as fixed.
 */
class AdapterStub implements KeyValueStore
{
    protected \Exception $exception;

    public function __construct(\Exception $exception)
    {
        $this->exception = $exception;
    }

    public function get(string $key, mixed &$token = null): mixed
    {
        throw $this->exception;
    }

    public function getMulti(array $keys, array &$tokens = null): array
    {
        throw $this->exception;
    }

    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        throw $this->exception;
    }

    public function setMulti(array $items, int $expire = 0): array
    {
        throw $this->exception;
    }

    public function delete(string $key): bool
    {
        throw $this->exception;
    }

    public function deleteMulti(array $keys): array
    {
        throw $this->exception;
    }

    public function add(string $key, mixed $value, int $expire = 0): bool
    {
        throw $this->exception;
    }

    public function replace(string $key, mixed $value, int $expire = 0): bool
    {
        throw $this->exception;
    }

    public function cas(mixed $token, string $key, mixed $value, int $expire = 0): bool
    {
        throw $this->exception;
    }

    public function increment(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        throw $this->exception;
    }

    public function decrement(string $key, int $offset = 1, int $initial = 0, int $expire = 0): int|false
    {
        throw $this->exception;
    }

    public function touch(string $key, int $expire): bool
    {
        throw $this->exception;
    }

    public function flush(): bool
    {
        throw $this->exception;
    }

    public function getCollection(string $name): KeyValueStore
    {
        throw $this->exception;
    }
}
