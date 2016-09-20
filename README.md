[![Scrapbook PHP cache](http://www.scrapbook.cash/public/logo_side.png)](http://www.scrapbook.cash)

[![Build status](https://api.travis-ci.org/matthiasmullie/scrapbook.svg?branch=master)](https://travis-ci.org/matthiasmullie/scrapbook)
[![Code coverage](http://img.shields.io/codecov/c/github/matthiasmullie/scrapbook.svg)](https://codecov.io/github/matthiasmullie/scrapbook)
[![Code quality](http://img.shields.io/scrutinizer/g/matthiasmullie/scrapbook.svg)](https://scrutinizer-ci.com/g/matthiasmullie/scrapbook)
[![Latest version](http://img.shields.io/packagist/v/matthiasmullie/scrapbook.svg)](https://packagist.org/packages/matthiasmullie/scrapbook)
[![Downloads total](http://img.shields.io/packagist/dt/matthiasmullie/scrapbook.svg)](https://packagist.org/packages/matthiasmullie/scrapbook)
[![License](http://img.shields.io/packagist/l/matthiasmullie/scrapbook.svg)](https://github.com/matthiasmullie/scrapbook/blob/master/LICENSE)

Documentation: http://www.scrapbook.cash - API reference: http://docs.scrapbook.cash

## Adapters

[Memcached](http://www.scrapbook.cash/adapters/memcached/),
[Redis](http://www.scrapbook.cash/adapters/redis/),
[Couchbase](http://www.scrapbook.cash/adapters/couchbase/),
[APC](http://www.scrapbook.cash/adapters/apc/),
[MySQL](http://www.scrapbook.cash/adapters/mysql/),
[SQLite](http://www.scrapbook.cash/adapters/sqlite/),
[PostgreSQL](http://www.scrapbook.cash/adapters/postgresql/),
[Flysystem](http://www.scrapbook.cash/adapters/flysystem/),
[MemoryStore](http://www.scrapbook.cash/adapters/memory/)


## Interfaces

3 interfaces are available & they work with all adapters.

### KeyValueStore

KeyValueStore is inspired by the Memcached API (driver model). It'll let you do
the most advanced cache operations & is easiest to use.

Here's an example:

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

A detailed list of the KeyValueStore interface & it's methods can be found in
the [documentation](http://www.scrapbook.cash/interfaces/key-value-store/).


### PSR-6 CacheItemPoolInterface & CacheItemInterface

PSR-6 (a PHP-FIG standard) is a different approach (pool model): there's 1 class
to interact with the cache backend (Pool) & one to represent the cache value
(Item).
This interface is supported by multiple cache implementations, so there won't be
any vendor lock-in

However, it doesn't offer much more than basic `get`, `set` & `delete`
functionality (which is quite often more than enough!)

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

A detailed list of the PSR-6 interface & it's methods can be found in the
[documentation](http://www.scrapbook.cash/interfaces/psr-cache/).


### PSR-16 CacheInterface & CounterInterface

PSR-16 is a second PHP-FIG cache standard. It's a driver model just like
KeyValueStore, and it works very much in the same way.
This interface is supported by multiple cache implementations, so there won't be
any vendor lock-in

However, it also doesn't offer much more than basic `get`, `set` & `delete`
functionality (which is quite often more than enough!)

```php
// boilerplate code example with Memcached, but any
// MatthiasMullie\Scrapbook\KeyValueStore adapter will work
$client = new \Memcached();
$client->addServer('localhost', 11211);
$cache = new \MatthiasMullie\Scrapbook\Adapters\Memcached($client);

// create Simplecache object from cache engine
$simplecache = new \MatthiasMullie\Scrapbook\Psr16\SimpleCache($cache);

// get value from cache
$value = $simplecache->get('key');

// ... or store a new value to cache
$simplecache->set('key', 'updated-value');
```

A detailed list of the PSR-16 interface & it's methods can be found in the
[documentation](http://www.scrapbook.cash/interfaces/psr-simplecache/).


## Extras

### Local buffer

[BufferedStore](http://www.scrapbook.cash/extras/buffered-cache/) helps
avoid repeat requests to your real cache by keeping the value in memory. That
way, you don't have to do that in your application - just keep querying that
cache!


### Transactions

Just like database transactions,
[TransactionalStore](http://www.scrapbook.cash/extras/transactional-cache/)
lets you defer cache writes to a later point in time, until you're ready to
commit all of it (or rollback.) You can even nest multiple transactions!


### Stampede protection

Cache stampedes can happen when you get a sudden surge of traffic but the data
is not yet in cache.
[StampedeProtector](http://www.scrapbook.cash/extras/stampede-protector/)
will make sure that only 1 request will generate the result & the others just
wait until it pops up in cache, instead of cripling your servers.


### Sharding

When you have too much data for (or requests to) 1 little server, this'll let
you [shard](http://www.scrapbook.cash/extras/shard/) it over multiple
cache servers.
All data will automatically be distributed evenly across your server pool, so
all the individual cache servers only get a fraction of the data & traffic.


## Installation

Simply add a dependency on matthiasmullie/scrapbook to your composer.json file
if you use [Composer](https://getcomposer.org/) to manage the dependencies of
your project:

```sh
composer require matthiasmullie/scrapbook
```

Although it's recommended to use Composer, you can actually include these files
anyway you want.

Just take a look at this "[build your cache](http://www.scrapbook.cash/)"
section to generate the exact configuration you'd like to use (adapter,
interface, extras) and some example code.


## License

Scrapbook is [MIT](http://opensource.org/licenses/MIT) licensed.
