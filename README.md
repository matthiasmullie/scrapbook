[![Scrapbook PHP cache](https://www.scrapbook.cash/public/logo_side.png)](https://www.scrapbook.cash)

[![Build status](https://img.shields.io/github/workflow/status/matthiasmullie/scrapbook/test-suite?style=flat-square)](https://github.com/matthiasmullie/scrapbook/actions/workflows/test.yml)
[![Code coverage](https://img.shields.io/codecov/c/github/matthiasmullie/scrapbook?style=flat-square)](https://codecov.io/github/matthiasmullie/scrapbook)
[![Latest version](https://img.shields.io/packagist/v/matthiasmullie/scrapbook?style=flat-square)](https://packagist.org/packages/matthiasmullie/scrapbook)
[![Downloads total](https://img.shields.io/packagist/dt/matthiasmullie/scrapbook?style=flat-square)](https://packagist.org/packages/matthiasmullie/scrapbook)
[![License](https://img.shields.io/packagist/l/matthiasmullie/scrapbook?style=flat-square)](https://github.com/matthiasmullie/scrapbook/blob/master/LICENSE)

Documentation: https://www.scrapbook.cash - API reference: https://docs.scrapbook.cash

# Table of contents

* [Installation & usage](#installation--usage)
* [Adapters](#adapters)
  * [Memcached](#memcached)
  * [Redis](#redis)
  * [Couchbase](#couchbase)
  * [APC(u)](#apcu)
  * [MySQL](#mysql)
  * [PostgreSQL](#postgresql)
  * [SQLite](#sqlite)
  * [Filesystem](#filesystem)
  * [Memory](#memory)
* [Features](#features)
  * [Local buffer](#local-buffer)
  * [Transactions](#transactions)
  * [Stampede protection](#stampede-protection)
  * [Sharding](#sharding)
* [Interfaces](#interfaces)
  * [KeyValueStore](#keyvaluestore)
  * [psr/cache](#psrcache)
  * [psr/simple-cache](#psrsimple-cache)
* [Collections](#collections)
* [Compatibility](#compatibility)
* [License](#license)


# Installation & usage

Simply add a dependency on matthiasmullie/scrapbook to your composer.json file
if you use [Composer](https://getcomposer.org/) to manage the dependencies of
your project:

```sh
composer require matthiasmullie/scrapbook
```

The exact bootstrapping will depend on which adapter, features and interface
you will want to use, all of which are detailed below.

This library is built in layers that are all
[KeyValueStore](https://docs.scrapbook.cash/master/class-MatthiasMullie.Scrapbook.KeyValueStore.html)
implementations that you can wrap inside one another if you want to add more
features.

Here's a simple example: a Memcached-backed psr/cache with stampede protection.

```php
// create \Memcached object pointing to your Memcached server
$client = new \Memcached();
$client->addServer('localhost', 11211);
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create stampede protector layer over our real cache
$cache = new \MatthiasMullie\Scrapbook\Scale\StampedeProtector($cache);

// create Pool (psr/cache) object from cache engine
$pool = new \MatthiasMullie\Scrapbook\Psr6\Pool($cache);

// get item from Pool
$item = $pool->getItem('key');

// get item value
$value = $item->get();

// ... or change the value & store it to cache
$item->set('updated-value');
$pool->save($item);
```

Just take a look at this "[build your cache](https://www.scrapbook.cash/)"
section to generate the exact configuration you'd like to use (adapter,
interface, features) and some example code.


# Adapters


## Memcached

*Memcached is an in-memory key-value store for small chunks of arbitrary data
(strings, objects) from results of database calls, API calls, or page
rendering.*

The [PECL Memcached extension](https://pecl.php.net/package/memcached) is used
to interface with the Memcached server. Just provide a valid `\Memcached` object
to the Memcached adapter:

```php
// create \Memcached object pointing to your Memcached server
$client = new \Memcached();
$client->addServer('localhost', 11211);
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);
```


## Redis

*Redis is often referred to as a data structure server since keys can contain
strings, hashes, lists, sets, sorted sets, bitmaps and hyperloglogs.*

The [PECL Redis extension](https://pecl.php.net/package/redis) is used
to interface with the Redis server. Just provide a valid `\Redis` object
to the Redis adapter:

```php
// create \Redis object pointing to your Redis server
$client = new \Redis();
$client->connect('127.0.0.1');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Redis($client);
```


## Couchbase

*Engineered to meet the elastic scalability, consistent high performance,
always-on availability, and data mobility requirements of mission critical
applications.*

The [PECL Couchbase extension](https://pecl.php.net/package/couchbase) and
[couchbase/couchbase package](https://packagist.org/packages/couchbase/couchbase)
are used to interface with the Couchbase server. Just provide valid
`\Couchbase\Collection`, `\Couchbase\Management\BucketManager` and
`\Couchbase\Bucket` objects to the Couchbase adapter:

```php
// create \Couchbase\Bucket object pointing to your Couchbase server
$options = new \Couchbase\ClusterOptions();
$options->credentials('username', 'password');
$cluster = new \Couchbase\Cluster('couchbase://localhost', $options);
$bucket = $cluster->bucket('default');
$collection = $bucket->defaultCollection();
$bucketManager = $cluster->buckets();
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Couchbase($collection, $bucketManager, $bucket);
```


## APC(u)

*APC is a free and open opcode cache for PHP. Its goal is to provide a free,
open, and robust framework for caching and optimizing PHP intermediate code.*

With APC, there is no "cache server", the data is just cached on the executing
machine and available to all PHP processes on that machine. The PECL
[APC](https://pecl.php.net/package/APC) or [APCu](https://pecl.php.net/package/APCu)
extensions can be used.

```php
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Apc();
```


## MySQL

*MySQL is the world's most popular open source database. MySQL can
cost-effectively help you deliver high performance, scalable database
applications.*

While a database is not a genuine cache, it can also serve as key-value store.
Just don't expect the same kind of performance you'd expect from a dedicated
cache server.

But there could be a good reason to use a database-based cache: it's convenient
if you already use a database and it may have other benefits (like persistent
storage, replication.)

```php
// create \PDO object pointing to your MySQL server
$client = new PDO('mysql:dbname=cache;host=127.0.0.1', 'root', '');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\MySQL($client);
```

## PostgreSQL

*PostgreSQL has a proven architecture that has earned it a strong reputation for
reliability, data integrity, and correctness.*

While a database is not a genuine cache, it can also serve as key-value store.
Just don't expect the same kind of performance you'd expect from a dedicated
cache server.

But there could be a good reason to use a database-based cache: it's convenient
if you already use a database and it may have other benefits (like persistent
storage, replication.)

```php
// create \PDO object pointing to your PostgreSQL server
$client = new PDO('pgsql:user=postgres dbname=cache password=');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\PostgreSQL($client);
```


## SQLite

*SQLite is a software library that implements a self-contained, serverless,
zero-configuration, transactional SQL database engine.*

While a database is not a genuine cache, it can also serve as key-value store.
Just don't expect the same kind of performance you'd expect from a dedicated
cache server.

```php
// create \PDO object pointing to your SQLite server
$client = new PDO('sqlite:cache.db');
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\SQLite($client);
```


## Filesystem

*While it's not the fastest kind of cache in terms of I/O access times, opening
a file usually still beats redoing expensive computations.*

The filesystem-based adapter uses `league\flysystem` to abstract away the file
operations, and will work with all kinds of storage that `league\filesystem`
provides.

```php
// create Flysystem object
$adapter = new \League\Flysystem\Adapter\Local('/path/to/cache', LOCK_EX);
$filesystem = new \League\Flysystem\Filesystem($adapter);
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\Flysystem($filesystem);
```


## Memory

*PHP can keep data in memory, too! PHP arrays as storage are particularly useful
to run tests against, since you don't have to install any other service.*

Stuffing values in memory is mostly useless: they'll drop out at the end of the
request. Unless you're using these cached values more than once in the same
request, cache will always be empty and there's little reason to use this cache.

However, it is extremely useful when unittesting: you can run your entire test
suite on this memory-based store, instead of setting up cache services and
making sure they're in a pristine state...

```php
// create Scrapbook KeyValueStore object
$cache = new \MatthiasMullie\Scrapbook\Adapters\MemoryStore();
```


# Features

In addition to the default cache functionality (like `get` & `set`), Scrapbook
comes with a few neat little features. These are all implemented in their own
little object that implements KeyValueStore, and wraps around a KeyValueStore.
In other words, any feature can just be wrapped inside another one or on top
of any adapter.


## Local buffer

BufferedStore helps reduce requests to your real cache. If you need the request
the same value more than once (from various places in your code), it can be a
pain to keep that value around. Requesting it again from cache would be easier,
but then you get some latency from the connection to the cache server.

BufferedStore will keep known values (items that you've already requested or
written yourself) in memory. Every time you need that value in the same request,
it'll just get it from memory instead of going out to the cache server.

Just wrap the BufferedStore layer around your adapter (or other features):

```php
// create buffered cache layer over our real cache
$cache = new \MatthiasMullie\Scrapbook\Buffered\BufferedStore($cache);
```


## Transactions

TransactionalStore makes it possible to defer writes to a later point in time.
Similar to transactions in databases, all deferred writes can be rolled back or
committed all at once to ensure the data that is stored is reliable and
complete. All of it will be stored, or nothing at all.

You may want to process code throughout your codebase, but not commit it any
changes until everything has successfully been validated & written to permanent
storage.

While inside a transaction, you don't have to worry about data consistency.
Inside a transaction, even if it has not yet been committed, you'll always be
served the one you intend to store. In other words, when you write a new value
to cache but have not yet committed it, you'll still get that value when you
query for it. Should you rollback, or fail to commit (because data stored by
another process caused your commit to fail), then you'll get the original value
from cache instead of the one your intended to commit.

Just wrap the TransactionalStore layer around your adapter (or other features):

```php
// create transactional cache layer over our real cache
$cache = new \MatthiasMullie\Scrapbook\Buffered\TransactionalStore($cache);
```

And TA-DA, you can use transactions!

```php
// begin a transaction
$cache->begin();

// set a value
// it won't be stored in real cache until commit() is called
$cache->set('key', 'value'); // returns true

// get a value
// it won't get it from the real cache (where it is not yet set), it'll be read
// from PHP memory
$cache->get('key'); // returns 'value'

// now commit write operations, this will effectively propagate the update to
// 'key' to the real cache
$cache->commit();

// ... or rollback, to discard uncommitted changes!
$cache->rollback();
```


## Stampede protection

A cache stampede happens when there are a lot of requests for data that is not
currently in cache, causing a lot of concurrent complex operations. For example:

* cache expires for something that is often under very heavy load
* sudden unexpected high load on something that is likely to not be in cache

In those cases, this huge amount of requests for data that is not in cache at
that time causes that expensive operation to be executed a lot of times, all at
once.

StampedeProtector is designed counteract that. If a value can't be found in
cache, a placeholder will be stored to indicate it was requested but didn't
exist. Every follow-up request for a short period of time will find that
indication and know another process is already generating that result, so those
will just wait until it becomes available, instead of crippling the servers.

Just wrap the StampedeProtector layer around your adapter (or other features):

```php
// create stampede protector layer over our real cache
$cache = new \MatthiasMullie\Scrapbook\Scale\StampedeProtector($cache);
```


## Sharding

When you have too much data for (or requests to) 1 little server, this'll let
you shard it over multiple cache servers. All data will automatically be
distributed evenly across your server pool, so all the individual cache servers
only get a fraction of the data & traffic.

Pass the individual KeyValueStore objects that compose the cache server pool
into this constructor & the data will be sharded over them according to the
order the cache servers were passed into this constructor (so make sure to
always keep the order the same.)

The sharding is spread evenly and all cache servers will roughly receive the
same amount of cache keys. If some servers are bigger than others, you can
offset this by adding that cache server's KeyValueStore object more than once.

Data can even be sharded among different adapters: one server in the shard pool
can be Redis while another can be Memcached. Not sure why you would want to do
that, but you could!

Just wrap the Shard layer around your adapter (or other features):

```php
// boilerplate code example with Redis, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Redis();
$client->connect('192.168.1.100');
$cache1 = new \MatthiasMullie\Scrapbook\Adapters\Redis($client);

// a second Redis server...
$client2 = new \Redis();
$client2->connect('192.168.1.101');
$cache2 = new \MatthiasMullie\Scrapbook\Adapters\Redis($client);

// create shard layer over our real caches
// now $cache will automatically distribute the data across both servers
$cache = new \MatthiasMullie\Scrapbook\Scale\Shard($cache1, $cache2);
```


# Interfaces

Scrapbook supports 3 different interfaces. There's the Scrapbook-specific
KeyValueStore, and then there are 2 PSR interfaces put forward by the PHP FIG.


## KeyValueStore

KeyValueStore is the cornerstone of this project. It is the interface that
provides the most cache operations: `get`, `getMulti`, `set`, `setMulti`,
`delete`, `deleteMulti`, `add`, `replace`, `cas`, `increment`, `decrement`,
`touch` & `flush`.

If you've ever used Memcached before, KeyValueStore will look very similar,
since it's inspired by/modeled after that API.

All adapters & features implement this interface. If you have complex cache
needs (like being able to `cas`), this is the one to stick to.

A detailed list of the KeyValueStore interface & its methods can be found in
the [documentation](https://www.scrapbook.cash/interfaces/key-value-store/).


## psr/cache

[PSR-6](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-6-cache.md)
(a PHP-FIG standard) is a drastically different cache model than KeyValueStore &
psr/simple-cache: instead of directly querying values from the cache, psr/cache
basically operates on value objects (`Item`) to perform the changes, which then
feed back to the cache (`Pool`.)

It doesn't let you do too many operations. If `get`, `set`, `delete` (and their
*multi counterparts) and `delete` is all you need, you're probably better off
using this (or [psr/simple-cache, see below](#psrsimple-cache)) as this interface
is also supported by other cache libraries.

You can easily use psr/cache by wrapping it around any KeyValueStore object:

```php
// create Pool object from Scrapbook KeyValueStore object
$pool = new \MatthiasMullie\Scrapbook\Psr6\Pool($cache);

// get item from Pool
$item = $pool->getItem('key');

// get item value
$value = $item->get();

// ... or change the value & store it to cache
$item->set('updated-value');
$pool->save($item);
```

A detailed list of the PSR-6 interface & its methods can be found in the
[documentation](https://www.scrapbook.cash/interfaces/psr-cache/).


## psr/simple-cache

[PSR-16](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-16-simple-cache.md)
(a PHP-FIG standard) is a second PHP-FIG cache standard. It's a driver model
just like KeyValueStore, and it works very much in the same way.

It doesn't let you do too many operations. If `get`, `set`, `delete` (and their
*multi counterparts) and `delete` is all you need, you're probably better off
using this (or [psr/cache, see above](#psrcache)) as this interface is also
supported by other cache libraries.

You can easily use psr/simple-cache by wrapping it around any KeyValueStore
object:

```php
// create Simplecache object from Scrapbook KeyValueStore object
$simplecache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

// get value from cache
$value = $simplecache->get('key');

// ... or store a new value to cache
$simplecache->set('key', 'updated-value');
```

A detailed list of the PSR-16 interface & its methods can be found in the
[documentation](https://www.scrapbook.cash/interfaces/psr-simplecache/).


# Collections

Collections, or namespaces if you wish, are isolated cache subsets that will
only ever provide access to the values within that context.

It is not possible to set/fetch data across collections. Setting the same key in
2 different collections will store 2 different values that can only be retrieved
from their respective collection.

Flushing a collection will only flush those specific keys and will leave keys in
other collections untouched.

Flushing the server, however, will wipe out everything, including data in
any of the collections on that server.

Here's a simple example:

```php
// let's create a Memcached cache object
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

$articleCache = $cache->collection('articles');
$sessionCache = $cache->collection('sessions');

// all of these are different keys
$cache->set('key', 'value one');
$articleCache->set('key', 'value two');
$sessionCache->set('key', 'value three');

// this clears our the entire 'articles' subset (thus removing 'value two'),
// while leaving everything else untouched
$articleCache->flush();

// this removes everything from the server, including all of its subsets
// ('value one' and 'value three' will also be deleted)
$cache->flush();
```

`getCollection` is available on all KeyValueStore implementations (so for every
adapter & feature that may wrap around it) and also returns a KeyValueStore
object. While it is not part of the PSR interfaces, you can create your
collections first and then wrap all of your collections inside their own
psr/cache or psr/simple-cache representations, like so:

```php
$articleCache = $cache->collection('articles');
$sessionCache = $cache->collection('sessions');

// create Pool objects from both KeyValueStore collections
$articlePool = new \MatthiasMullie\Scrapbook\Psr6\Pool($articleCache);
$sessionPool = new \MatthiasMullie\Scrapbook\Psr6\Pool($sessionCache);
```


# Compatibility

Where possible, Scrapbook supports PHP versions 5.3 up to the current
version, as well as HHVM. Differences in the exact implementation of the cache
backends across these versions & platforms will be mitigated within Scrapbook
to ensure uniform behavior.

Compatibility with all of these versions & platforms can be confirmed with the
provided docker-compose config for PHP versions 5.6 and onwards.
Even though officially supported by Scrapbook, lower PHP versions (down to 5.3)
are no longer actively being tested because dependencies required for testing
have since diverged too much.

Cache backends that do not have an implementation for a particular version or
platform ([Flysystem](#filesystem), on older PHP versions) are obviously not
supported.

Compatibility with old software versions will not be broken easily. Not unless
there is a compelling reason to do so, like security or performance
implications. Syntactic sugar is not a reason to break compatibility.


# License

Scrapbook is [MIT](https://opensource.org/licenses/MIT) licensed.
