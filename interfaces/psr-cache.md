---
layout: interface
title: PSR-6 cache
description: The PHP-FIG (Framework Interop Group) have defined this pool driver cache standard. Scrapbook has an implementation that builds on key-value-store, so it works with all adapters.
weight: 1
icon: fa fa-gears
namespace: MatthiasMullie\Scrapbook\Psr6
---

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

<hr class="sep10">

[PSR-6](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-6-cache.md)
(a PHP-FIG standard) is a drastically different cache model than KeyValueStore &
psr/simplecache: instead of directly querying values from the cache, psr/cache
basically operates on value objects (`Item`) to perform the changes, which then
feed back to the cache (`Pool`.)

It doesn't let you do too many operations. If `get`, `set`, `delete` (and their
*multi counterparts) and `delete` is all you need, you're probably better off
using this (or psr/simplecache) as this interface is also supported by other
cache libraries.

This interface bridges the gap between KeyValueStore based adapters & features,
and PSR-6: any of the Scrapbook tools are accessible in a PSR-6 compatible
manner.

<hr class="sep20">

## Methods Pool

<hr class="sep10">

{% include psr-6-pool.html %}

<hr class="sep20">

## Methods Item

<hr class="sep10">

{% include psr-6-item.html %}
