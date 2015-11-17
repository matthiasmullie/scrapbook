![Scrapbook PHP cache](http://www.scrapbook.cash/public/logo_side.png)

[![Build status](https://api.travis-ci.org/matthiasmullie/scrapbook.svg?branch=master)](https://travis-ci.org/matthiasmullie/scrapbook)
[![Code coverage](http://img.shields.io/coveralls/matthiasmullie/scrapbook.svg)](https://coveralls.io/r/matthiasmullie/scrapbook)
[![Code quality](http://img.shields.io/scrutinizer/g/matthiasmullie/scrapbook.svg)](https://scrutinizer-ci.com/g/matthiasmullie/scrapbook)
[![Latest version](http://img.shields.io/packagist/v/matthiasmullie/scrapbook.svg)](https://packagist.org/packages/matthiasmullie/scrapbook)
[![Downloads total](http://img.shields.io/packagist/dt/matthiasmullie/scrapbook.svg)](https://packagist.org/packages/matthiasmullie/scrapbook)
[![License](http://img.shields.io/packagist/l/matthiasmullie/scrapbook.svg)](https://github.com/matthiasmullie/scrapbook/blob/master/LICENSE)


# KeyValueStore adapters

Scrapbook KeyValueStore defines an interface for 3rd parties to implement
against, ensuring that multiple cache engines are supported without having to
rewrite. Just pull the adapter of your preferred cache engine!

A default adapter (MemoryStore) is included: it's a no-cache cache where all
data is lost as soon as your application terminates. This is an ideal cache to
test your implementation against, as it doesn't require you to install any
server or dependencies and always starts from a pristine state.

Other adapters:
* APC
* Memcached
* Redis
* Couchbase
* MySQL
* SQLite
* PostgreSQL
* Flysystem


## Example usage

```php
// create \Memcached object pointing to your Memcached server
$client = new \Memcached();
$client->addServer('localhost', 11211);
// create Scrapbook cache object
// (example with Memcached, but any adapter works the same)
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// set a value
$cache->set('key', 'value'); // returns true

// get a value
$cache->get('key'); // returns 'value'
```


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


# BufferedStore & TransactionalStore

## BufferedStore

BufferedStore helps reduce requests to your real cache. If you need the request
the same value more than once (from various places in your code), it can be a
pain to keep that value around. Requesting it again from cache would be easier,
but then you get some latency from the connection to the cache server.

BufferedStore will keep known values (items that you've already requested or
written yourself) in memory. Every time you need that value in the same request,
it'll just get it from memory instead of going back to the cache server.

BufferedStore just wraps around any KeyValueStore and is itself a KeyValueStore.
Just use it like you would call any cache, but enjoy the reduced headaches about
requesting the same date multiple times!


## TransactionalStore

TransactionalStore is similar to BufferedStore. It wraps around any
KeyValueStore, but provides that one with transactional capabilities. It makes
it possible to defer writes to a later point in time.

You may want to process code throughout your codebase, but not commit it any
changes until everything has successfully been validated & written to permanent
storage.

TransactionalStore lets you start a transaction, which will defer all writes
until you're actually ready to do them (or roll them back). Meanwhile, it keeps
all your changes around in a local cache, so follow-up operations on your cache
operate on the correct date, even though it hasn't yet been committed to your
real cache.

It too is a KeyValueStore, but adds 3 methods:

### begin()

Initiate a transaction: this will defer all writes to real cache until
commit() is called.

Transactions can be nested. A new transaction can be begin while another is
already in progress. Committing the nested transaction will apply the changes
to the one that was already in progress. Changes will only be committed to
cache once the original transaction is committed.
Rolling back a nested transaction will only roll back those changes and leave
changes in the parent transaction alone.

### commit(): bool

Commits the deferred updates to real cache.
If the any write fails, all subsequent writes will be aborted & all keys
that had already been written to will be restored to their original value.

### rollback()

Roll back all scheduled changes.


# Scale

## StampedeProtector

A cache stampede happens when there are a lot of requests for data that is not
currently in cache. Examples could be:
* cache expires for something that is often under very heavy load
* sudden unexpected high load on something that is likely to not be in cache
In those cases, this huge amount of requests for data that is not at that time
in cache, causes that expensive operation to be executed a lot of times, all at
once.

StampedeProtector is designed counteract that. If a value can't be found in
cache, something will be stored to another key to indicate it was requested but
didn't exist. Every follow-up request for a short period of time will find that
indication and know another process is already generating that result, so those
will just wait until it becomes available, instead of crippling the servers.


# PSR-6

Adds a PSR-6 layer so that any KeyValueStore-compatible adapter (or
buffered/transactional cache) can be accessed in a PSR-6 compatible manner.

PSR-6 has not yet been finalized - if it changes, so will this code in response.


## Usage

```php
// boilerplate code example with Memcached, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create Pool object from cache engine
$pool = new \MatthiasMullie\Scrapbook\Psr6\Pool($cache);

// get item from Pool
$item = $pool->getItem('key');

// get item value
$value = $item->get();

// ... or change the value & store it to cache
$item->set('updated-value');
$pool->save($item);
```


## Methods Pool

### getItem($key): Item

Returns a Cache Item representing the specified key.

### getItems(array $keys = array()): Item[]

Returns a traversable set of cache items.

### hasItem($key): bool

Confirms if the cache contains specified cache item.

### clear(): bool

Deletes all items in the pool.

### deleteItem($key): bool

Removes the item from the pool.

### deleteItems(array $keys): bool

Removes multiple items from the pool.

### save(CacheItemInterface $item): bool

Persists a cache item immediately.

### saveDeferred(CacheItemInterface $item): bool

Sets a cache item to be persisted later.

### commit(): bool

Persists any deferred cache items.


## Methods Item

### getKey(): string

Returns the key for the current cache item.

### get(): mixed

Retrieves the value of the item from the cache associated with this object's key.

### isHit(): bool

Confirms if the cache item lookup resulted in a cache hit.

### set($value): static

Sets the value represented by this cache item.

### expiresAt($expiration): static

Sets the expiration time for this cache item.

### expiresAfter($expiration): static

Sets the expiration time for this cache item.


## Installation

Simply add a dependency on matthiasmullie/scrapbook to your composer.json file if you use [Composer](https://getcomposer.org/) to manage the dependencies of your project:

```sh
composer require matthiasmullie/scrapbook
```

Although it's recommended to use Composer, you can actually include these files anyway you want.


## License

Scrapbook is [MIT](http://opensource.org/licenses/MIT) licensed.
