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

### getMulti(array $keys, array &$tokens = null): mixed[]

Retrieves multiple items at once (reduced network traffic compared to
individual operations)

Return value will be an associative array. Keys missing in cache will be
omitted from the array.

### set($key, $value, $expire = 0): bool

Stores an item, regardless of whether or not it already exists.

### setMulti(array $items, $expire = 0): bool

Store multiple items at once (reduced network traffic compared to
individual operations)

### delete($key): bool

Deletes an item from the cache.
Returns true if item existed & was successfully deleted, false otherwise.

### deleteMulti(array $keys): bool

Deletes multiple items at once (reduced network traffic compared to
individual operations)

### add($key, $value, $expire = 0)

Adds an item under new key.
Operation fails (returns false) if key already exists on server.

### replace($key, $value, $expire = 0)

Replaces an item.
Operation fails (returns false) if key does not yet exist on server.

### cas($token, $key, $value, $expire = 0)

Replaces an item in 1 atomic operation, to ensure it didn't change since
it was originally read (= when the CAS token was issued)
Operation fails (returns false) if CAS token didn't match.

### increment($key, $offset = 1, $initial = 0, $expire = 0)

Increments a counter value.

### decrement($key, $offset = 1, $initial = 0, $expire = 0)

Decrements a counter value.

### touch($key, $expire)

Updates an item's expiration time without altering the stored value.

### flush()

Clears the entire cache.


## Installation

Simply add a dependency on scrapbook/key-value-store to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require scrapbook/key-value-store
```

Although it's recommended to use Composer, you can actually include these files anyway you want.


## License

Scrapbook is [MIT](http://opensource.org/licenses/MIT) licensed.
