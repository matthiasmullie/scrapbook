# Scrapbook

[![Build status](https://api.travis-ci.org/scrapbook/key-value-store.svg?branch=master)](https://travis-ci.org/scrapbook/key-value-store)
[![Code coverage](http://img.shields.io/coveralls/scrapbook/key-value-store.svg)](https://coveralls.io/r/scrapbook/key-value-store)
[![Code quality](http://img.shields.io/scrutinizer/g/scrapbook/key-value-store.svg)](https://scrutinizer-ci.com/g/scrapbook/key-value-store)
[![Latest version](http://img.shields.io/packagist/v/scrapbook/key-value-store.svg)](https://packagist.org/packages/scrapbook/key-value-store)
[![Downloads total](http://img.shields.io/packagist/dt/scrapbook/key-value-store.svg)](https://packagist.org/packages/scrapbook/key-value-store)
[![License](http://img.shields.io/packagist/l/scrapbook/key-value-store.svg)](https://github.com/scrapbook/key-value-store/blob/master/LICENSE)


Scrapbook key-value-store defines an interface for 3rd parties to implement
against, ensuring that multiple cache engines are supported without having to
rewrite. Just pull the adapter of your preferred cache engine!

A default adapter (MemoryStore) is included: it's a no-cache cache where all
data is lost as soon as your application terminates. This is an ideal cache to
test your implementation against, as it doesn't require you to install any
server or dependencies and always starts from a pristine state.


## Methods

### get($key, &$token = null): mixed|bool

Retrieves an item from the cache.

Optionally, an 2nd variable can be passed to this function. It will be
filled with a value that can be used for cas()

### getMulti(array $keys, array &$tokens = null): mixed[]

Retrieves multiple items at once.

Return value will be an associative array in [key => value] format. Keys
missing in cache will be omitted from the array.

Optionally, an 2nd variable can be passed to this function. It will be
filled with values that can be used for cas(), in an associative array in
[key => token] format. Keys missing in cache will be omitted from the
array.

getMulti is preferred over multiple individual get operations as you'll
get them all in 1 request.

### set($key, $value, $expire = 0): bool

Stores a value, regardless of whether or not the key already exists (in
which case it will overwrite the existing value for that key)

Return value is a boolean true when the operation succeeds, or false on
failure.

### setMulti(array $items, $expire = 0): bool[]

Store multiple values at once.

Return value will be an associative array in [key => status] form, where
status is a boolean true for success, or false for failure.

setMulti is preferred over multiple individual set operations as you'll
set them all in 1 request.

### delete($key): bool

Deletes an item from the cache.
Returns true if item existed & was successfully deleted, false otherwise.

Return value is a boolean true when the operation succeeds, or false on
failure.

### deleteMulti(array $keys): bool[]

Deletes multiple items at once (reduced network traffic compared to
individual operations)

Return value will be an associative array in [key => status] form, where
status is a boolean true for success, or false for failure.

### add($key, $value, $expire = 0): bool

Adds an item under new key.

This operation fails (returns false) if the key already exists in cache.
If the operation succeeds, true will be returned.

### replace($key, $value, $expire = 0): bool

Replaces an item.

This operation fails (returns false) if the key does not yet exist in
cache. If the operation succeeds, true will be returned.

### cas($token, $key, $value, $expire = 0): bool

Replaces an item in 1 atomic operation, to ensure it didn't change since
it was originally read, when the CAS token was issued.

This operation fails (returns false) if the CAS token didn't match with
what's currently in cache, when a new value has been written to cache
after we've fetched it. If the operation succeeds, true will be returned.

### increment($key, $offset = 1, $initial = 0, $expire = 0): int|bool

Increments a counter value, or sets an initial value if it does not yet
exist.

The new counter value will be returned if this operation succeeds, or
false for failure (e.g. when the value currently in cache is not a
number, in which case it can't be incremented)

### decrement($key, $offset = 1, $initial = 0, $expire = 0): int|bool

Decrements a counter value, or sets an initial value if it does not yet
exist.

The new counter value will be returned if this operation succeeds, or
false for failure (e.g. when the value currently in cache is not a
number, in which case it can't be decremented)

### touch($key, $expire): bool

Updates an item's expiration time without altering the stored value.

Return value is a boolean true when the operation succeeds, or false on
failure.

### flush(): bool

Clears the entire cache.


## Installation

Simply add a dependency on scrapbook/key-value-store to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require scrapbook/key-value-store
```

Although it's recommended to use Composer, you can actually include these files anyway you want.


## License

Scrapbook is [MIT](http://opensource.org/licenses/MIT) licensed.
